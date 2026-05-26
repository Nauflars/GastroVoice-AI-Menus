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
        $slot = TimeSlot::fromString($query->timeSlot)->alignToGrid(60);

        $availableTables = $this->checker->getAvailableTables($restaurantId, $date, $slot);

        return [
            'isAvailable'     => $availableTables > 0,
            'availableTables' => $availableTables,
            'maxTables'       => ReservationAvailabilityChecker::MAX_TABLES,
            'date'            => $query->date,
            'timeSlot'        => $slot->toString(),
        ];
    }
}
