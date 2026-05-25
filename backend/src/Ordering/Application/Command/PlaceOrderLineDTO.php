<?php

declare(strict_types=1);

namespace App\Ordering\Application\Command;

final class PlaceOrderLineDTO
{
    public function __construct(
        public readonly string $menuItemId,
        public readonly string $menuItemName,
        public readonly int $quantity,
        public readonly float $unitPrice,
        public readonly string $currency = 'EUR',
    ) {}
}
