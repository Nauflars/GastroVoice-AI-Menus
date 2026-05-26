<?php

declare(strict_types=1);

namespace App\Ordering\Domain\Repository;

use App\Ordering\Domain\Entity\Order;
use App\Ordering\Domain\ValueObject\OrderStatus;
use Symfony\Component\Uid\Uuid;

interface OrderRepositoryInterface
{
    public function findById(Uuid $id): ?Order;

    /** @return Order[] */
    public function findByRestaurant(Uuid $restaurantId, ?OrderStatus $status = null): array;

    public function save(Order $order): void;
}
