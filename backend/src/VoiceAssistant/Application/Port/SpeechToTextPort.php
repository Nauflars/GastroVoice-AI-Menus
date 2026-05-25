<?php

declare(strict_types=1);

namespace App\VoiceAssistant\Application\Port;

interface SpeechToTextPort
{
    public function transcribe(string $audioPath): string;
}
