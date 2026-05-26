<?php

declare(strict_types=1);

namespace App\Menu\Domain\Event;

use Symfony\Component\Uid\Uuid;

final class MenuItemAvailabilityChanged
{
    public function __construct(
        public readonly Uuid $menuItemId,
        public readonly bool $isAvailable,
    ) {}
}
