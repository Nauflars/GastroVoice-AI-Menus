<?php

declare(strict_types=1);

namespace App\Menu\Application\Port;

use App\Menu\Application\DTO\MenuImportPreview;

interface AIMenuExtractorPort
{
    /**
     * @param string $imageBase64 Base64-encoded image data
     */
    public function extract(string $imageBase64, string $mimeType): MenuImportPreview;
}
