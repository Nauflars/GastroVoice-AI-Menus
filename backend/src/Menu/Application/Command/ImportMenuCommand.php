<?php

declare(strict_types=1);

namespace App\Menu\Application\Command;

use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ImportMenuCommand
{
    public function __construct(
        public readonly string $restaurantId,
        public readonly UploadedFile $image,
    ) {}
}
