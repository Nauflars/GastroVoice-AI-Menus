<?php

declare(strict_types=1);

namespace App\Menu\Infrastructure\Persistence;

use App\Menu\Domain\Entity\MenuCategory;
use App\Menu\Domain\Repository\MenuCategoryRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineMenuCategoryRepository implements MenuCategoryRepositoryInterface
{
    public function __construct(private EntityManagerInterface $em) {}

    public function findById(Uuid $id): ?MenuCategory
    {
        return $this->em->find(MenuCategory::class, $id);
    }

    public function findActiveByRestaurant(Uuid $restaurantId): array
    {
        return $this->em->createQueryBuilder()
            ->select('c')
            ->from(MenuCategory::class, 'c')
            ->where('c.restaurantId = :rid')
            ->andWhere('c.isActive = true')
            ->orderBy('c.displayOrder', 'ASC')
            ->setParameter('rid', $restaurantId)
            ->getQuery()
            ->getResult();
    }

    public function save(MenuCategory $category): void
    {
        $this->em->persist($category);
        $this->em->flush();
    }
}
