<?php

declare(strict_types=1);

namespace App\Reservations\Domain\ValueObject;

final class TimeSlot
{
    private function __construct(private readonly string $value) {}

    public static function fromString(string $time): self
    {
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time)) {
            throw new \DomainException(sprintf('Invalid time slot format "%s". Expected HH:MM.', $time));
        }
        return new self($time);
    }

    public function alignToGrid(int $slotDurationMinutes): self
    {
        [$h, $m] = explode(':', $this->value);
        $totalMinutes = (int)$h * 60 + (int)$m;
        $aligned = (int)floor($totalMinutes / $slotDurationMinutes) * $slotDurationMinutes;
        return new self(sprintf('%02d:%02d', (int)floor($aligned / 60), $aligned % 60));
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
