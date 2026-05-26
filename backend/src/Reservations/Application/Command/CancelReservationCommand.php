<?php

declare(strict_types=1);

namespace App\Reservations\Application\Command;

final class CancelReservationCommand
{
    public function __construct(public readonly string $reservationId) {}
}
