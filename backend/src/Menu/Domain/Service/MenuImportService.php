<?php

declare(strict_types=1);

namespace App\Menu\Domain\Service;

use App\Menu\Application\DTO\MenuImportPreview;
use App\Menu\Domain\Entity\MenuCategory;
use App\Menu\Domain\Entity\MenuItem;
use App\Menu\Domain\Repository\MenuCategoryRepositoryInterface;
use App\Menu\Domain\Repository\MenuItemRepositoryInterface;
use App\Menu\Domain\ValueObject\CategoryName;
use App\Menu\Domain\ValueObject\Price;
use Symfony\Component\Uid\Uuid;

final class MenuImportService
{
    public function __construct(
        private MenuCategoryRepositoryInterface $categories,
        private MenuItemRepositoryInterface $items,
    ) {}

    public function persist(Uuid $restaurantId, MenuImportPreview $preview): void
    {
        foreach ($preview->categories as $categoryDTO) {
            $category = MenuCategory::create(
                $restaurantId,
                CategoryName::of($categoryDTO->name),
            );
            $this->categories->save($category);

            foreach ($categoryDTO->items as $itemDTO) {
                $item = MenuItem::create(
                    $category->getId(),
                    $itemDTO->name,
                    $itemDTO->description,
                    Price::of($itemDTO->price, $itemDTO->currency),
                );
                $this->items->save($item);
            }
        }
    }
}
