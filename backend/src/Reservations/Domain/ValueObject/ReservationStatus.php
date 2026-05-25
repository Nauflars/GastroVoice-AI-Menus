<?php

declare(strict_types=1);

namespace App\Reservations\Domain\ValueObject;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    private const TRANSITIONS = [
        'pending'   => ['confirmed', 'cancelled'],
        'confirmed' => ['cancelled', 'no_show'],
        'cancelled' => [],
        'no_show'   => [],
    ];

    public function transitionTo(self $next): self
    {
        if (!in_array($next->value, self::TRANSITIONS[$this->value], true)) {
            throw new \DomainException(sprintf(
                'Cannot transition reservation from "%s" to "%s".',
                $this->value,
                $next->value,
            ));
        }
        return $next;
    }

    public function isFinal(): bool
    {
        return in_array($this->value, ['cancelled', 'no_show'], true);
    }
}
