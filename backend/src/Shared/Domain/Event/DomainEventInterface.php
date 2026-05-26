<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

interface DomainEventInterface
{
    public function occurredAt(): \DateTimeImmutable;
}
