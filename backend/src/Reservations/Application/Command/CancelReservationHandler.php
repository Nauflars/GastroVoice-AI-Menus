<?php

declare(strict_types=1);

namespace App\Reservations\Application\Command;

use App\Reservations\Domain\Repository\ReservationRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class CancelReservationHandler
{
    public function __construct(private ReservationRepositoryInterface $repo) {}

    public function __invoke(CancelReservationCommand $command): void
    {
        $reservation = $this->repo->findById(Uuid::fromString($command->reservationId));
        if ($reservation === null) {
            throw new \DomainException('Reservation not found.');
        }
        $reservation->cancel();
        $this->repo->save($reservation);
    }
}
