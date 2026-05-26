<?php

declare(strict_types=1);

namespace App\VoiceAssistant\Application\Port;

interface TextToSpeechPort
{
    /** Returns path to the generated audio file (mp3). */
    public function synthesize(string $text, string $voice = 'alloy'): string;
}
