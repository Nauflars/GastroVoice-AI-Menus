<?php

declare(strict_types=1);

namespace App\Menu\Application\DTO;

final class MenuImportItemDTO
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $description,
        public readonly float $price,
        public readonly string $currency,
        /** @var string[] */
        public readonly array $allergens = [],
    ) {}
}
