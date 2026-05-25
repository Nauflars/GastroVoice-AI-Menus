<?php

declare(strict_types=1);

namespace App\Reservations\Application\Command;

final class CreateReservationCommand
{
    public function __construct(
        public readonly string $restaurantId,
        public readonly string $date,
        public readonly string $timeSlot,
        public readonly int $numPeople,
        public readonly string $customerName,
        public readonly int $restaurantCapacity,
        public readonly ?string $customerPhone = null,
        public readonly ?string $customerEmail = null,
        public readonly ?string $notes = null,
    ) {}
}
