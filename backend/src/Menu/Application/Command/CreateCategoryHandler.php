<?php

declare(strict_types=1);

namespace App\Menu\Application\Command;

use App\Menu\Domain\Entity\MenuCategory;
use App\Menu\Domain\Repository\MenuCategoryRepositoryInterface;
use App\Menu\Domain\ValueObject\CategoryName;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class CreateCategoryHandler
{
    public function __construct(private MenuCategoryRepositoryInterface $categories) {}

    public function __invoke(CreateCategoryCommand $command): MenuCategory
    {
        $category = MenuCategory::create(
            Uuid::fromString($command->restaurantId),
            CategoryName::of($command->name),
            $command->displayOrder,
        );
        $this->categories->save($category);
        return $category;
    }
}
