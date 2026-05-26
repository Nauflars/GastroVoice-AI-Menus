<?php

declare(strict_types=1);

namespace App\Menu\Application\Command;

use App\Menu\Domain\Repository\MenuCategoryRepositoryInterface;
use App\Menu\Domain\ValueObject\CategoryName;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class UpdateCategoryHandler
{
    public function __construct(private MenuCategoryRepositoryInterface $categories) {}

    public function __invoke(UpdateCategoryCommand $command): void
    {
        $category = $this->categories->findById(Uuid::fromString($command->categoryId));
        if ($category === null) {
            throw new \DomainException('Category not found.');
        }
        $category->rename(CategoryName::of($command->name));
        $category->reorder($command->displayOrder);
        $this->categories->save($category);
    }
}
