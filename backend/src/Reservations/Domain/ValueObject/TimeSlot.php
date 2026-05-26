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

    /** Strict factory that only accepts full-hour slots like 13:00. */
    public static function fromHourString(string $time): self
    {
        $slot = self::fromString($time);
        [, $minutes] = explode(':', $time);
        if ((int) $minutes !== 0) {
            throw new \DomainException(sprintf('Time slot "%s" is not on the hour. Reservations are only accepted in hourly slots (e.g. 13:00, 14:00).', $time));
        }
        return $slot;
    }

    public static function fromHour(int $hour): self
    {
        if ($hour < 0 || $hour > 23) {
            throw new \DomainException(sprintf('Invalid hour "%d". Must be between 0 and 23.', $hour));
        }
        return new self(sprintf('%02d:00', $hour));
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
