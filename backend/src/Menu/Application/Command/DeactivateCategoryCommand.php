<?php

declare(strict_types=1);

namespace App\Menu\Application\Command;

final class DeactivateCategoryCommand
{
    public function __construct(public readonly string $categoryId) {}
}
