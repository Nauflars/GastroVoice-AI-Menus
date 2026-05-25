<?php

declare(strict_types=1);

namespace App\Menu\Domain\Repository;

use App\Menu\Domain\Entity\MenuCategory;
use Symfony\Component\Uid\Uuid;

interface MenuCategoryRepositoryInterface
{
    public function findById(Uuid $id): ?MenuCategory;

    /** @return MenuCategory[] */
    public function findActiveByRestaurant(Uuid $restaurantId): array;

    public function save(MenuCategory $category): void;
}
