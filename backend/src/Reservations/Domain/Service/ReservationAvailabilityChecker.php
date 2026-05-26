<?php

declare(strict_types=1);

namespace App\Reservations\Domain\Service;

use App\Reservations\Domain\Repository\ReservationRepositoryInterface;
use App\Reservations\Domain\ValueObject\TimeSlot;
use Symfony\Component\Uid\Uuid;

final class ReservationAvailabilityChecker
{
    public const MAX_TABLES = 10;

    public function __construct(private ReservationRepositoryInterface $repo) {}

    public function getAvailableTables(
        Uuid $restaurantId,
        \DateTimeImmutable $date,
        TimeSlot $timeSlot,
    ): int {
        $booked = $this->repo->countTablesForSlot($restaurantId, $date, $timeSlot);
        return max(0, self::MAX_TABLES - $booked);
    }

    public function isAvailable(
        Uuid $restaurantId,
        \DateTimeImmutable $date,
        TimeSlot $timeSlot,
    ): bool {
        return $this->getAvailableTables($restaurantId, $date, $timeSlot) > 0;
    }

    /** @deprecated Use isAvailable() without capacity param */
    public function getAvailableCapacity(
        Uuid $restaurantId,
        \DateTimeImmutable $date,
        TimeSlot $timeSlot,
        int $totalCapacity,
    ): int {
        return $this->getAvailableTables($restaurantId, $date, $timeSlot);
    }
}
