<?php

declare(strict_types=1);

namespace App\Menu\Application\Command;

final class CreateCategoryCommand
{
    public function __construct(
        public readonly string $restaurantId,
        public readonly string $name,
        public readonly int $displayOrder = 0,
    ) {}
}
