<?php

declare(strict_types=1);

namespace App\Tests\Unit\Menu\Application\Handler;

use App\Menu\Application\Command\CreateMenuItemCommand;
use App\Menu\Application\Command\CreateMenuItemHandler;
use App\Menu\Application\Query\GetActiveMenuHandler;
use App\Menu\Application\Query\GetActiveMenuQuery;
use App\Menu\Domain\Entity\MenuCategory;
use App\Menu\Domain\Entity\MenuItem;
use App\Menu\Domain\Repository\MenuCategoryRepositoryInterface;
use App\Menu\Domain\Repository\MenuItemRepositoryInterface;
use App\Menu\Domain\ValueObject\CategoryName;
use App\Menu\Domain\ValueObject\Price;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class MenuHandlerTest extends TestCase
{
    private MenuCategoryRepositoryInterface&MockObject $categories;
    private MenuItemRepositoryInterface&MockObject $items;

    protected function setUp(): void
    {
        $this->categories = $this->createMock(MenuCategoryRepositoryInterface::class);
        $this->items = $this->createMock(MenuItemRepositoryInterface::class);
    }

    public function testCreateMenuItemSavesItem(): void
    {
        $categoryId = Uuid::v7();
        $category = MenuCategory::create(Uuid::v7(), CategoryName::of('Starters'));

        $this->categories->method('findById')->willReturn($category);
        $this->items->expects($this->once())->method('save');

        $handler = new CreateMenuItemHandler($this->categories, $this->items);
        $item = $handler(new CreateMenuItemCommand(
            (string) $categoryId,
            'Soup',
            'Hot soup',
            8.50,
        ));

        $this->assertSame('Soup', $item->getName());
    }

    public function testCreateMenuItemFailsIfCategoryNotFound(): void
    {
        $this->categories->method('findById')->willReturn(null);

        $this->expectException(\DomainException::class);

        $handler = new CreateMenuItemHandler($this->categories, $this->items);
        $handler(new CreateMenuItemCommand((string) Uuid::v7(), 'Soup', null, 8.50));
    }

    public function testGetActiveMenuReturnsStructuredData(): void
    {
        $restaurantId = Uuid::v7();
        $category = MenuCategory::create($restaurantId, CategoryName::of('Starters'));
        $item = MenuItem::create($category->getId(), 'Soup', null, Price::of(8.50));

        $this->categories->method('findActiveByRestaurant')->willReturn([$category]);
        $this->items->method('findActiveByCategory')->willReturn([$item]);

        $handler = new GetActiveMenuHandler($this->categories, $this->items);
        $result = $handler(new GetActiveMenuQuery((string) $restaurantId));

        $this->assertCount(1, $result);
        $this->assertSame('Starters', $result[0]['name']);
        $this->assertCount(1, $result[0]['items']);
        $this->assertSame('Soup', $result[0]['items'][0]['name']);
    }
}
