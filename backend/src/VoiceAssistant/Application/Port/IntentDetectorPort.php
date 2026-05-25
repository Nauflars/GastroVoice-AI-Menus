<?php

declare(strict_types=1);

namespace App\VoiceAssistant\Application\Port;

interface IntentDetectorPort
{
    /**
     * @param array<array{role: string, content: string}> $messages
     * @param array<string, mixed> $restaurantContext
     */
    public function detect(array $messages, array $restaurantContext): IntentDetectionResult;
}
