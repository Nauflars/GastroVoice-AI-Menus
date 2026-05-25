<?php

declare(strict_types=1);

namespace App\Menu\Infrastructure\Persistence;

use App\Menu\Domain\Entity\MenuItem;
use App\Menu\Domain\Repository\MenuItemRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineMenuItemRepository implements MenuItemRepositoryInterface
{
    public function __construct(private EntityManagerInterface $em) {}

    public function findById(Uuid $id): ?MenuItem
    {
        return $this->em->find(MenuItem::class, $id);
    }

    public function findActiveByCategory(Uuid $categoryId): array
    {
        return $this->em->createQueryBuilder()
            ->select('i')
            ->from(MenuItem::class, 'i')
            ->where('i.categoryId = :cid')
            ->andWhere('i.isAvailable = true')
            ->setParameter('cid', $categoryId)
            ->getQuery()
            ->getResult();
    }

    public function save(MenuItem $item): void
    {
        $this->em->persist($item);
        $this->em->flush();
    }
}
