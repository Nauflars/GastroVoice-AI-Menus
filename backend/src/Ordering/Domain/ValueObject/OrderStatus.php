<?php

declare(strict_types=1);

namespace App\Ordering\Domain\ValueObject;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    private const TRANSITIONS = [
        self::Pending->value => [self::Confirmed->value, self::Cancelled->value],
        self::Confirmed->value => [self::Preparing->value, self::Cancelled->value],
        self::Preparing->value => [self::Ready->value, self::Cancelled->value],
        self::Ready->value => [self::Delivered->value],
        self::Delivered->value => [],
        self::Cancelled->value => [],
    ];

    public function transitionTo(self $next): self
    {
        $allowed = self::TRANSITIONS[$this->value];
        if (!in_array($next->value, $allowed, true)) {
            throw new \DomainException(
                sprintf('Cannot transition order status from "%s" to "%s".', $this->value, $next->value),
            );
        }
        return $next;
    }

    public function isFinal(): bool
    {
        return $this === self::Delivered || $this === self::Cancelled;
    }
}
