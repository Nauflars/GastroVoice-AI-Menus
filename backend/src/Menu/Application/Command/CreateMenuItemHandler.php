<?php

declare(strict_types=1);

namespace App\Menu\Application\Command;

use App\Menu\Domain\Entity\MenuItem;
use App\Menu\Domain\Repository\MenuCategoryRepositoryInterface;
use App\Menu\Domain\Repository\MenuItemRepositoryInterface;
use App\Menu\Domain\ValueObject\Price;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class CreateMenuItemHandler
{
    public function __construct(
        private MenuCategoryRepositoryInterface $categories,
        private MenuItemRepositoryInterface $items,
    ) {}

    public function __invoke(CreateMenuItemCommand $command): MenuItem
    {
        $category = $this->categories->findById(Uuid::fromString($command->categoryId));
        if ($category === null) {
            throw new \DomainException('Category not found.');
        }

        $item = MenuItem::create(
            $category->getId(),
            $command->name,
            $command->description,
            Price::of($command->price, $command->currency),
        );
        $this->items->save($item);
        return $item;
    }
}
