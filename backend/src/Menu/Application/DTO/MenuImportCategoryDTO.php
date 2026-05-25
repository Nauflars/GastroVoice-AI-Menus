<?php

declare(strict_types=1);

namespace App\Menu\Application\DTO;

final class MenuImportCategoryDTO
{
    /**
     * @param MenuImportItemDTO[] $items
     */
    public function __construct(
        public readonly string $name,
        public readonly array $items,
    ) {}
}
