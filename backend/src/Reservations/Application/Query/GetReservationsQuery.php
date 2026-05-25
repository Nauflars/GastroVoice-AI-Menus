<?php

declare(strict_types=1);

namespace App\Reservations\Application\Query;

final class GetReservationsQuery
{
    public function __construct(
        public readonly string $restaurantId,
        public readonly ?string $date = null,
        public readonly ?string $status = null,
    ) {}
}
