<?php

declare(strict_types=1);

namespace App\Ordering\Infrastructure\Persistence;

use App\Ordering\Domain\Entity\Order;
use App\Ordering\Domain\Entity\OrderLine;
use App\Ordering\Domain\Repository\OrderRepositoryInterface;
use App\Ordering\Domain\ValueObject\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineOrderRepository implements OrderRepositoryInterface
{
    public function __construct(private EntityManagerInterface $em) {}

    public function findById(Uuid $id): ?Order
    {
        $order = $this->em->find(Order::class, $id);
        if ($order !== null) {
            $this->hydrateLines($order);
        }
        return $order;
    }

    public function findByRestaurant(Uuid $restaurantId, ?OrderStatus $status = null): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('o')
            ->from(Order::class, 'o')
            ->where('o.restaurantId = :rid')
            ->orderBy('o.createdAt', 'DESC')
            ->setParameter('rid', $restaurantId);

        if ($status !== null) {
            $qb->andWhere('o.statusValue = :status')->setParameter('status', $status->value);
        }

        $orders = $qb->getQuery()->getResult();
        foreach ($orders as $order) {
            $this->hydrateLines($order);
        }
        return $orders;
    }

    public function save(Order $order): void
    {
        $this->em->persist($order);
        foreach ($order->getLines() as $line) {
            $this->em->persist($line);
        }
        $this->em->flush();
    }

    private function hydrateLines(Order $order): void
    {
        $lines = $this->em->createQueryBuilder()
            ->select('l')
            ->from(OrderLine::class, 'l')
            ->where('l.orderId = :oid')
            ->setParameter('oid', $order->getId())
            ->getQuery()
            ->getResult();

        $order->reconstituteLinesFromPersistence($lines);
    }
}
