<?php

declare(strict_types=1);

namespace App\Reservations\Application\Query;

use App\Reservations\Domain\Service\ReservationAvailabilityChecker;
use App\Reservations\Domain\ValueObject\TimeSlot;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class CheckAvailabilityHandler
{
    public function __construct(private ReservationAvailabilityChecker $checker) {}

    public function __invoke(CheckAvailabilityQuery $query): array
    {
        $restaurantId = Uuid::fromString($query->restaurantId);
        $date = new \DateTimeImmutable($query->date);
        $slot = TimeSlot::fromString($query->timeSlot);

        $available = $this->checker->getAvailableCapacity(
            $restaurantId,
            $date,
            $slot,
            $query->restaurantCapacity,
        );

        return [
            'isAvailable'       => $available >= $query->numPeople,
            'availableCapacity' => $available,
            'requestedPeople'   => $query->numPeople,
            'date'              => $query->date,
            'timeSlot'          => $query->timeSlot,
        ];
    }
}
