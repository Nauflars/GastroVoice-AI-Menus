<?php

declare(strict_types=1);

namespace App\Reservations\Application\Command;

use App\Reservations\Domain\Entity\Reservation;
use App\Reservations\Domain\Exception\SlotFullException;
use App\Reservations\Domain\Repository\ReservationRepositoryInterface;
use App\Reservations\Domain\Service\ReservationAvailabilityChecker;
use App\Reservations\Domain\ValueObject\TimeSlot;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class CreateReservationHandler
{
    public function __construct(
        private ReservationRepositoryInterface $repo,
        private ReservationAvailabilityChecker $checker,
    ) {}

    public function __invoke(CreateReservationCommand $command): Reservation
    {
        $restaurantId = Uuid::fromString($command->restaurantId);
        $date = new \DateTimeImmutable($command->date);
        $slot = TimeSlot::fromString($command->timeSlot);

        if (!$this->checker->isAvailable($restaurantId, $date, $slot, $command->restaurantCapacity, $command->numPeople)) {
            throw new SlotFullException(sprintf(
                'No availability for slot %s on %s.',
                $command->timeSlot,
                $command->date,
            ));
        }

        $reservation = Reservation::create(
            $restaurantId,
            $date,
            $slot,
            $command->numPeople,
            $command->customerName,
            $command->customerPhone,
            $command->customerEmail,
            $command->notes,
        );

        $this->repo->save($reservation);
        return $reservation;
    }
}
