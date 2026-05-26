<?php

declare(strict_types=1);

namespace App\Ordering\Application\Query;

final class GetOrdersQuery
{
    public function __construct(
        public readonly string $restaurantId,
        public readonly ?string $status = null,
    ) {}
}
