<?php

declare(strict_types=1);

namespace App\RestaurantManagement\Application\Command;

use Symfony\Component\Uid\Uuid;

final class UpdateRestaurantCommand
{
    /**
     * @param array<int, array{dayOfWeek: int, isClosed: bool, openTime: ?string, closeTime: ?string}> $openingHours
     */
    public function __construct(
        public readonly Uuid $restaurantId,
        public readonly string $name,
        public readonly string $address,
        public readonly string $phone,
        public readonly int $seatCapacity,
        public readonly int $slotDurationMinutes,
        public readonly string $timezone,
        public readonly array $openingHours = [],
    ) {
    }
}
