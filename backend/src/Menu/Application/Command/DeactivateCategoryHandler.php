<?php

declare(strict_types=1);

namespace App\Menu\Application\Command;

use App\Menu\Domain\Repository\MenuCategoryRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class DeactivateCategoryHandler
{
    public function __construct(private MenuCategoryRepositoryInterface $categories) {}

    public function __invoke(DeactivateCategoryCommand $command): void
    {
        $category = $this->categories->findById(Uuid::fromString($command->categoryId));
        if ($category === null) {
            throw new \DomainException('Category not found.');
        }
        $category->deactivate();
        $this->categories->save($category);
    }
}
