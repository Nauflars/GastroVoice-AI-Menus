<?php

declare(strict_types=1);

namespace App\RestaurantManagement\Domain\Repository;

use App\RestaurantManagement\Domain\Entity\Restaurant;
use Symfony\Component\Uid\Uuid;

interface RestaurantRepositoryInterface
{
    public function findById(Uuid $id): ?Restaurant;

    public function findByAdminUserId(Uuid $adminUserId): ?Restaurant;

    public function save(Restaurant $restaurant): void;
}
