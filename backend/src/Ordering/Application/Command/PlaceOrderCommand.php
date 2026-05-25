<?php

declare(strict_types=1);

namespace App\Ordering\Application\Command;

final class PlaceOrderCommand
{
    /**
     * @param PlaceOrderLineDTO[] $lines
     */
    public function __construct(
        public readonly string $restaurantId,
        public readonly string $source,
        public readonly ?string $tableNumber,
        public readonly ?string $customerPhone,
        public readonly array $lines,
        public readonly ?string $notes = null,
    ) {}
}
