<?php

declare(strict_types=1);

namespace App\Menu\Domain\Event;

use Symfony\Component\Uid\Uuid;

final class MenuItemCreated
{
    public function __construct(
        public readonly Uuid $menuItemId,
        public readonly Uuid $categoryId,
        public readonly string $name,
        public readonly float $price,
    ) {}
}
