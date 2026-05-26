<?php

declare(strict_types=1);

namespace App\RestaurantManagement\Application\Query;

use Symfony\Component\Uid\Uuid;

final class GetRestaurantQuery
{
    public function __construct(
        public readonly Uuid $restaurantId,
    ) {
    }
}
