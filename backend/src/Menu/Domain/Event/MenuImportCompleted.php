<?php

declare(strict_types=1);

namespace App\Menu\Domain\Event;

use Symfony\Component\Uid\Uuid;

final class MenuImportCompleted
{
    public function __construct(
        public readonly Uuid $restaurantId,
        public readonly int $categoriesImported,
        public readonly int $itemsImported,
    ) {}
}
