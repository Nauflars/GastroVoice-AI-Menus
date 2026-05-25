<?php

declare(strict_types=1);

namespace App\Menu\Application\Command;

final class UpdateCategoryCommand
{
    public function __construct(
        public readonly string $categoryId,
        public readonly string $name,
        public readonly int $displayOrder,
    ) {}
}
