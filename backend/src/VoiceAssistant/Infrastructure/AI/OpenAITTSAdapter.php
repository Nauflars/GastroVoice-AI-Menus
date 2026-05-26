<?php

declare(strict_types=1);

namespace App\VoiceAssistant\Infrastructure\AI;

use App\VoiceAssistant\Application\Port\TextToSpeechPort;
use OpenAI\Client;

final class OpenAITTSAdapter implements TextToSpeechPort
{
    public function __construct(private Client $openai) {}

    public function synthesize(string $text): string
    {
        $response = $this->openai->audio()->speech([
            'model'  => 'tts-1',
            'input'  => $text,
            'voice'  => 'alloy',
            'format' => 'mp3',
        ]);

        $path = sys_get_temp_dir() . '/' . uniqid('tts_', true) . '.mp3';
        file_put_contents($path, $response);
        return $path;
    }
}
