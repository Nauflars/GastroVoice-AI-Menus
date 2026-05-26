<?php

declare(strict_types=1);

namespace App\VoiceAssistant\Infrastructure\Http;

use App\Shared\Domain\Bus\CommandBusInterface;
use App\VoiceAssistant\Application\Command\ProcessVoiceTurnCommand;
use App\VoiceAssistant\Application\Port\SpeechToTextPort;
use App\VoiceAssistant\Application\Port\TextToSpeechPort;
use App\VoiceAssistant\Infrastructure\AI\RealtimeSessionConfigBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/api/voice')]
final class VoiceController extends AbstractController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private SpeechToTextPort $stt,
        private TextToSpeechPort $tts,
        private HttpClientInterface $httpClient,
        private RealtimeSessionConfigBuilder $realtimeConfig,
        #[Autowire(env: 'OPENAI_API_KEY')]
        private string $openaiApiKey,
    ) {}

    /**
     * Text-in / text-out simulation endpoint.
     * POST /api/voice/simulate
     * Body: { restaurantId, text, sessionId?, callerId? }
     */
    #[Route('/simulate', methods: ['POST'])]
    public function simulate(Request $request): JsonResponse
    {
        $data = $request->toArray();

        $result = $this->commandBus->dispatch(new ProcessVoiceTurnCommand(
            $data['restaurantId'] ?? '',
            $data['text'] ?? '',
            $data['sessionId'] ?? null,
            $data['callerId'] ?? 'simulator',
            $data['restaurantContext'] ?? [],
        ));

        return $this->json([
            'sessionId' => $result->sessionId,
            'reply'     => $result->replyText,
            'intent'    => $result->intent,
        ]);
    }

    /**
     * Audio-in / audio-out endpoint (used by Asterisk AGI).
     * POST /api/voice/call  (multipart: audioFile, restaurantId, sessionId?)
     */
    #[Route('/call', methods: ['POST'])]
    public function call(Request $request): Response
    {
        $audioFile = $request->files->get('audioFile');
        if ($audioFile === null) {
            return $this->json(['error' => 'audioFile is required'], Response::HTTP_BAD_REQUEST);
        }

        $audioPath  = $audioFile->getRealPath();
        $transcript = $this->stt->transcribe($audioPath);

        $result = $this->commandBus->dispatch(new ProcessVoiceTurnCommand(
            $request->request->get('restaurantId', ''),
            $transcript,
            $request->request->get('sessionId'),
            $request->request->get('callerId', 'unknown'),
        ));

        $voice = $request->request->get('voice', 'alloy');
        $audioResponsePath = $this->tts->synthesize($result->replyText, $voice);

        return new BinaryFileResponse($audioResponsePath, Response::HTTP_OK, [
            'Content-Type' => 'audio/mpeg',
            'X-Session-Id' => $result->sessionId,
            'X-Intent'     => $result->intent,
            'X-Transcript' => $transcript,
        ]);
    }

    /**
     * Creates an OpenAI Realtime ephemeral token.
     * POST /api/voice/realtime-session
     * Body: { restaurantId, restaurantName? }
     */
    #[Route('/realtime-session', methods: ['POST'])]
    public function realtimeSession(Request $request): JsonResponse
    {
        $data           = $request->toArray();
        $restaurantId   = $data['restaurantId']   ?? '';
        $restaurantName = $data['restaurantName'] ?? 'el restaurante';
        $voice          = $data['voice']          ?? 'coral';

        $sessionConfig = $this->realtimeConfig->buildSessionConfig($restaurantName, $voice);

        try {
            $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/realtime/client_secrets', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openaiApiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => ['session' => $sessionConfig],
            ]);

            $sessionData = $response->toArray();

            return $this->json([
                'clientSecret'   => $sessionData['value'] ?? '',
                'restaurantId'   => $restaurantId,
                'restaurantName' => $restaurantName,
                'systemPrompt'   => $sessionConfig['instructions'],
                'tools'          => $sessionConfig['tools'],
            ]);
        } catch (\Throwable $e) {
            $detail = '';
            if (method_exists($e, 'getResponse')) {
                try { $detail = ' | ' . $e->getResponse()->getContent(false); } catch (\Throwable) {}
            }

            return $this->json(
                ['error' => 'Failed to create realtime session: ' . $e->getMessage() . $detail],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }

    /**
     * Returns Realtime session config for the media-bridge (internal/telephony).
     * No OpenAI call — just instructions + tools + model so the bridge can
     * open its own server-to-server Realtime WebSocket.
     *
     * GET /api/voice/telephony/session-config?restaurantName=...&voice=coral
     */
    #[Route('/telephony/session-config', methods: ['GET'])]
    public function telephonySessionConfig(Request $request): JsonResponse
    {
        $restaurantName = $request->query->get('restaurantName', 'el restaurante');
        $voice          = $request->query->get('voice', 'coral');

        $config = $this->realtimeConfig->buildSessionConfig($restaurantName, $voice);

        return $this->json($config);
    }

}
