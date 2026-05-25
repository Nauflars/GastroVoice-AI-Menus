<?php

declare(strict_types=1);

namespace App\VoiceAssistant\Infrastructure\Http;

use App\Shared\Domain\Bus\CommandBusInterface;
use App\VoiceAssistant\Application\Command\ProcessVoiceTurnCommand;
use App\VoiceAssistant\Application\Port\SpeechToTextPort;
use App\VoiceAssistant\Application\Port\TextToSpeechPort;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/voice')]
final class VoiceController extends AbstractController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private SpeechToTextPort $stt,
        private TextToSpeechPort $tts,
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

        $audioPath = $audioFile->getRealPath();
        $transcript = $this->stt->transcribe($audioPath);

        $result = $this->commandBus->dispatch(new ProcessVoiceTurnCommand(
            $request->request->get('restaurantId', ''),
            $transcript,
            $request->request->get('sessionId'),
            $request->request->get('callerId', 'unknown'),
        ));

        $audioResponsePath = $this->tts->synthesize($result->replyText);

        return new BinaryFileResponse($audioResponsePath, Response::HTTP_OK, [
            'Content-Type'        => 'audio/mpeg',
            'X-Session-Id'        => $result->sessionId,
            'X-Intent'            => $result->intent,
            'X-Transcript'        => $transcript,
        ]);
    }
}
