<?php

declare(strict_types=1);

namespace App\VoiceAssistant\Infrastructure\AI;

use App\VoiceAssistant\Application\Port\SpeechToTextPort;
use OpenAI\Client;

final class OpenAIWhisperAdapter implements SpeechToTextPort
{
    public function __construct(private Client $openai) {}

    public function transcribe(string $audioPath): string
    {
        $response = $this->openai->audio()->transcribe([
            'model' => 'whisper-1',
            'file'  => fopen($audioPath, 'r'),
            'language' => 'es',
        ]);

        return $response->text ?? '';
    }
}
