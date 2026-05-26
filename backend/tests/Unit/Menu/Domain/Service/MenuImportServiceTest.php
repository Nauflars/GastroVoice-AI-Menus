<?php

declare(strict_types=1);

namespace App\Tests\Unit\Menu\Domain\Service;

use App\Menu\Application\DTO\MenuImportCategoryDTO;
use App\Menu\Application\DTO\MenuImportItemDTO;
use App\Menu\Application\DTO\MenuImportPreview;
use App\Menu\Domain\Entity\MenuCategory;
use App\Menu\Domain\Entity\MenuItem;
use App\Menu\Domain\Repository\MenuCategoryRepositoryInterface;
use App\Menu\Domain\Repository\MenuItemRepositoryInterface;
use App\Menu\Domain\Service\MenuImportService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class MenuImportServiceTest extends TestCase
{
    private MenuCategoryRepositoryInterface&MockObject $categories;
    private MenuItemRepositoryInterface&MockObject $items;
    private MenuImportService $service;

    protected function setUp(): void
    {
        $this->categories = $this->createMock(MenuCategoryRepositoryInterface::class);
        $this->items = $this->createMock(MenuItemRepositoryInterface::class);
        $this->service = new MenuImportService($this->categories, $this->items);
    }

    public function testPersistCreatesCategoiesAndItems(): void
    {
        $preview = new MenuImportPreview('preview-1', [
            new MenuImportCategoryDTO('Starters', [
                new MenuImportItemDTO('Soup', 'Hot soup', 8.50, 'EUR', []),
                new MenuImportItemDTO('Salad', null, 6.00, 'EUR', ['gluten']),
            ]),
            new MenuImportCategoryDTO('Mains', [
                new MenuImportItemDTO('Pasta', 'With tomato', 12.00, 'EUR', []),
            ]),
        ]);

        $this->categories->expects($this->exactly(2))->method('save')
            ->with($this->isInstanceOf(MenuCategory::class));

        $this->items->expects($this->exactly(3))->method('save')
            ->with($this->isInstanceOf(MenuItem::class));

        $restaurantId = Uuid::v7();
        $this->service->persist($restaurantId, $preview);
    }

    public function testPersistEmptyPreviewSavesNothing(): void
    {
        $preview = new MenuImportPreview('preview-2', []);

        $this->categories->expects($this->never())->method('save');
        $this->items->expects($this->never())->method('save');

        $this->service->persist(Uuid::v7(), $preview);
    }

    public function testPersistCategoryWithNoItemsCreatesOnlyCategory(): void
    {
        $preview = new MenuImportPreview('preview-3', [
            new MenuImportCategoryDTO('Empty Category', []),
        ]);

        $this->categories->expects($this->once())->method('save');
        $this->items->expects($this->never())->method('save');

        $this->service->persist(Uuid::v7(), $preview);
    }
}
