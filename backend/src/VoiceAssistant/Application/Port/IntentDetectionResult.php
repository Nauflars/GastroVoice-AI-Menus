<?php

declare(strict_types=1);

namespace App\VoiceAssistant\Application\Port;

use App\VoiceAssistant\Domain\ValueObject\Intent;

final class IntentDetectionResult
{
    public function __construct(
        public readonly Intent $intent,
        /** @var array<string, mixed> */
        public readonly array $data,
        /** @var string[] */
        public readonly array $missingFields,
        public readonly string $reply,
    ) {}
}
