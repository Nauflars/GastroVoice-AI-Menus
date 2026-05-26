<?php

declare(strict_types=1);

namespace App\Reservations\Application\Query;

use App\Reservations\Domain\Entity\Reservation;
use App\Reservations\Domain\Repository\ReservationRepositoryInterface;
use App\Reservations\Domain\ValueObject\ReservationStatus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class GetReservationsHandler
{
    public function __construct(private ReservationRepositoryInterface $repo) {}

    public function __invoke(GetReservationsQuery $query): array
    {
        $date = $query->date !== null ? new \DateTimeImmutable($query->date) : null;
        $status = $query->status !== null ? ReservationStatus::from($query->status) : null;

        $items = $this->repo->findByRestaurant(
            Uuid::fromString($query->restaurantId),
            $date,
            $status,
        );

        return array_map(fn(Reservation $r) => [
            'id'            => (string) $r->getId(),
            'date'          => $r->getDate()->format('Y-m-d'),
            'timeSlot'      => $r->getTimeSlot()->toString(),
            'numPeople'     => $r->getNumPeople(),
            'customerName'  => $r->getCustomerName(),
            'customerPhone' => $r->getCustomerPhone(),
            'customerEmail' => $r->getCustomerEmail(),
            'notes'         => $r->getNotes(),
            'status'        => $r->getStatus()->value,
            'createdAt'     => $r->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], $items);
    }
}
