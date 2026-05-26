<?php

declare(strict_types=1);

namespace App\Menu\Application\Command;

final class CreateMenuItemCommand
{
    public function __construct(
        public readonly string $categoryId,
        public readonly string $name,
        public readonly ?string $description,
        public readonly float $price,
        public readonly string $currency = 'EUR',
    ) {}
}
