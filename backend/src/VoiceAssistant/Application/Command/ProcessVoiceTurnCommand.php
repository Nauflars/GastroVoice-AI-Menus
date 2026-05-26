<?php

declare(strict_types=1);

namespace App\VoiceAssistant\Application\Command;

final class ProcessVoiceTurnCommand
{
    public function __construct(
        public readonly string $restaurantId,
        public readonly string $userText,
        public readonly ?string $sessionId = null,
        public readonly string $callerId = 'unknown',
        /** @var array<string, mixed> */
        public readonly array $restaurantContext = [],
    ) {}
}
