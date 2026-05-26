<?php

declare(strict_types=1);

namespace App\VoiceAssistant\Infrastructure\AI;

use App\VoiceAssistant\Application\Port\TextToSpeechPort;
use OpenAI\Client;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class OpenAITTSAdapter implements TextToSpeechPort
{
    public function __construct(
        private Client $openai,
        #[Autowire(env: 'OPENAI_TTS_MODEL')]
        private string $model = 'tts-1',
        #[Autowire(env: 'OPENAI_TTS_VOICE')]
        private string $voice = 'alloy',
    ) {}

    public function synthesize(string $text, string $voice = 'alloy'): string
    {
        $response = $this->openai->audio()->speech([
            'model'  => $this->model,
            'input'  => $text,
            'voice'  => $voice,
            'format' => 'mp3',
        ]);

        $path = sys_get_temp_dir() . '/' . uniqid('tts_', true) . '.mp3';
        file_put_contents($path, $response);
        return $path;
    }
}
