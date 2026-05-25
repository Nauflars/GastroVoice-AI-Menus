<?php

declare(strict_types=1);

namespace App\Menu\Domain\Repository;

use App\Menu\Domain\Entity\MenuItem;
use Symfony\Component\Uid\Uuid;

interface MenuItemRepositoryInterface
{
    public function findById(Uuid $id): ?MenuItem;

    /** @return MenuItem[] */
    public function findActiveByCategory(Uuid $categoryId): array;

    public function save(MenuItem $item): void;
}
