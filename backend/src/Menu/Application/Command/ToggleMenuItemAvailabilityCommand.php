<?php

declare(strict_types=1);

namespace App\Menu\Application\Command;

final class ToggleMenuItemAvailabilityCommand
{
    public function __construct(public readonly string $itemId) {}
}
