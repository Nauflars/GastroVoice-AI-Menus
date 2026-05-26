<?php

declare(strict_types=1);

namespace App\Reservations\Application\Query;

final class CheckAvailabilityQuery
{
    public function __construct(
        public readonly string $restaurantId,
        public readonly string $date,
        public readonly string $timeSlot,
        public readonly int $numPeople = 1,
    ) {}
}
