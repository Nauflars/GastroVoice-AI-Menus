<?php

declare(strict_types=1);

namespace App\Reservations\Domain\Repository;

use App\Reservations\Domain\Entity\Reservation;
use App\Reservations\Domain\ValueObject\ReservationStatus;
use App\Reservations\Domain\ValueObject\TimeSlot;
use Symfony\Component\Uid\Uuid;

interface ReservationRepositoryInterface
{
    public function findById(Uuid $id): ?Reservation;

    /** @return Reservation[] */
    public function findByRestaurant(Uuid $restaurantId, ?\DateTimeImmutable $date = null, ?ReservationStatus $status = null): array;

    public function sumPeopleForSlot(Uuid $restaurantId, \DateTimeImmutable $date, TimeSlot $timeSlot): int;

    public function save(Reservation $reservation): void;
}
