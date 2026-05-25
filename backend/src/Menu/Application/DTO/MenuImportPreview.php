<?php

declare(strict_types=1);

namespace App\Menu\Application\DTO;

final class MenuImportPreview
{
    /**
     * @param MenuImportCategoryDTO[] $categories
     */
    public function __construct(
        public readonly string $previewId,
        public readonly array $categories,
    ) {}

    public function toArray(): array
    {
        return [
            'previewId' => $this->previewId,
            'categories' => array_map(fn(MenuImportCategoryDTO $c) => [
                'name' => $c->name,
                'items' => array_map(fn(MenuImportItemDTO $i) => [
                    'name' => $i->name,
                    'description' => $i->description,
                    'price' => $i->price,
                    'currency' => $i->currency,
                    'allergens' => $i->allergens,
                ], $c->items),
            ], $this->categories),
        ];
    }
}
