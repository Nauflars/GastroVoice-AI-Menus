<?php

declare(strict_types=1);

namespace App\RestaurantManagement\Application\Query;

use App\RestaurantManagement\Domain\Entity\Restaurant;
use App\RestaurantManagement\Domain\Repository\RestaurantRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetRestaurantHandler
{
    public function __construct(
        private readonly RestaurantRepositoryInterface $repository,
    ) {
    }

    public function __invoke(GetRestaurantQuery $query): Restaurant
    {
        $restaurant = $this->repository->findById($query->restaurantId);
        if (null === $restaurant) {
            throw new \DomainException(\sprintf('Restaurant "%s" not found.', $query->restaurantId));
        }

        return $restaurant;
    }
}
