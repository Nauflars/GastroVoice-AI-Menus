<?php

declare(strict_types=1);

namespace App\Reservations\Infrastructure\Persistence;

use App\Reservations\Domain\Entity\Reservation;
use App\Reservations\Domain\Repository\ReservationRepositoryInterface;
use App\Reservations\Domain\ValueObject\ReservationStatus;
use App\Reservations\Domain\ValueObject\TimeSlot;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineReservationRepository implements ReservationRepositoryInterface
{
    public function __construct(private EntityManagerInterface $em) {}

    public function findById(Uuid $id): ?Reservation
    {
        return $this->em->find(Reservation::class, $id);
    }

    public function findByRestaurant(Uuid $restaurantId, ?\DateTimeImmutable $date = null, ?ReservationStatus $status = null): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('r')
            ->from(Reservation::class, 'r')
            ->where('r.restaurantId = :rid')
            ->orderBy('r.date', 'ASC')
            ->addOrderBy('r.timeSlotValue', 'ASC')
            ->setParameter('rid', $restaurantId);

        if ($date !== null) {
            $qb->andWhere('r.date = :date')->setParameter('date', $date->format('Y-m-d'));
        }

        if ($status !== null) {
            $qb->andWhere('r.statusValue = :status')->setParameter('status', $status->value);
        }

        return $qb->getQuery()->getResult();
    }

    public function sumPeopleForSlot(Uuid $restaurantId, \DateTimeImmutable $date, TimeSlot $timeSlot): int
    {
        $result = $this->em->createQueryBuilder()
            ->select('SUM(r.numPeople) as total')
            ->from(Reservation::class, 'r')
            ->where('r.restaurantId = :rid')
            ->andWhere('r.date = :date')
            ->andWhere('r.timeSlotValue = :slot')
            ->andWhere("r.statusValue NOT IN (:finalStatuses)")
            ->setParameter('rid', $restaurantId)
            ->setParameter('date', $date->format('Y-m-d'))
            ->setParameter('slot', $timeSlot->toString())
            ->setParameter('finalStatuses', ['cancelled', 'no_show'])
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    public function countTablesForSlot(Uuid $restaurantId, \DateTimeImmutable $date, TimeSlot $timeSlot): int
    {
        $result = $this->em->createQueryBuilder()
            ->select('COUNT(r.id) as total')
            ->from(Reservation::class, 'r')
            ->where('r.restaurantId = :rid')
            ->andWhere('r.date = :date')
            ->andWhere('r.timeSlotValue = :slot')
            ->andWhere("r.statusValue NOT IN (:finalStatuses)")
            ->setParameter('rid', $restaurantId)
            ->setParameter('date', $date->format('Y-m-d'))
            ->setParameter('slot', $timeSlot->toString())
            ->setParameter('finalStatuses', ['cancelled', 'no_show'])
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    public function save(Reservation $reservation): void
    {
        $this->em->persist($reservation);
        $this->em->flush();
    }
}
