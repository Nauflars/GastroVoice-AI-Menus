<?php

declare(strict_types=1);

namespace App\Menu\Application\Query;

final class GetActiveMenuQuery
{
    public function __construct(public readonly string $restaurantId) {}
}
