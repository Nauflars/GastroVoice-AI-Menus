<?php

declare(strict_types=1);

namespace App\Reservations\Domain\Service;

use App\Reservations\Domain\Repository\ReservationRepositoryInterface;
use App\Reservations\Domain\ValueObject\TimeSlot;
use Symfony\Component\Uid\Uuid;

final class ReservationAvailabilityChecker
{
    public function __construct(private ReservationRepositoryInterface $repo) {}

    public function getAvailableCapacity(
        Uuid $restaurantId,
        \DateTimeImmutable $date,
        TimeSlot $timeSlot,
        int $totalCapacity,
    ): int {
        $booked = $this->repo->sumPeopleForSlot($restaurantId, $date, $timeSlot);
        return max(0, $totalCapacity - $booked);
    }

    public function isAvailable(
        Uuid $restaurantId,
        \DateTimeImmutable $date,
        TimeSlot $timeSlot,
        int $totalCapacity,
        int $requestedPeople,
    ): bool {
        return $this->getAvailableCapacity($restaurantId, $date, $timeSlot, $totalCapacity) >= $requestedPeople;
    }
}
