<?php

declare(strict_types=1);

namespace App\RestaurantManagement\Infrastructure\Persistence;

use App\RestaurantManagement\Domain\Entity\Restaurant;
use App\RestaurantManagement\Domain\Repository\RestaurantRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineRestaurantRepository implements RestaurantRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function findById(Uuid $id): ?Restaurant
    {
        return $this->em->find(Restaurant::class, $id);
    }

    public function findByAdminUserId(Uuid $adminUserId): ?Restaurant
    {
        return $this->em->createQueryBuilder()
            ->select('r')
            ->from(Restaurant::class, 'r')
            ->join('App\Identity\Domain\Entity\AdminUser', 'u', 'WITH', 'u.restaurantId = r.id')
            ->where('u.id = :adminId')
            ->setParameter('adminId', $adminUserId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function save(Restaurant $restaurant): void
    {
        $this->em->persist($restaurant);
        $this->em->flush();
    }
}
