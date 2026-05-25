<?php

declare(strict_types=1);

namespace App\Menu\Application\Query;

use App\Menu\Domain\Repository\MenuCategoryRepositoryInterface;
use App\Menu\Domain\Repository\MenuItemRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class GetActiveMenuHandler
{
    public function __construct(
        private MenuCategoryRepositoryInterface $categories,
        private MenuItemRepositoryInterface $items,
    ) {}

    public function __invoke(GetActiveMenuQuery $query): array
    {
        $restaurantId = Uuid::fromString($query->restaurantId);
        $categories = $this->categories->findActiveByRestaurant($restaurantId);

        $result = [];
        foreach ($categories as $category) {
            $items = $this->items->findActiveByCategory($category->getId());
            $result[] = [
                'id' => (string) $category->getId(),
                'name' => $category->getName()->value(),
                'displayOrder' => $category->getDisplayOrder(),
                'items' => array_map(fn($item) => [
                    'id' => (string) $item->getId(),
                    'name' => $item->getName(),
                    'description' => $item->getDescription(),
                    'price' => $item->getPrice()->amount(),
                    'currency' => $item->getPrice()->currency(),
                    'isAvailable' => $item->isAvailable(),
                ], $items),
            ];
        }
        return $result;
    }
}
