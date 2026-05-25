<?php

declare(strict_types=1);

namespace App\Menu\Application\Command;

final class ConfirmMenuImportCommand
{
    public function __construct(
        public readonly string $previewId,
        public readonly string $restaurantId,
    ) {}
}
