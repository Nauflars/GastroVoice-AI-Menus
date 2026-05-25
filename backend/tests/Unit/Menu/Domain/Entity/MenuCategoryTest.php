<?php

declare(strict_types=1);

namespace App\Tests\Unit\Menu\Domain\Entity;

use App\Menu\Domain\Entity\MenuCategory;
use App\Menu\Domain\ValueObject\CategoryName;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class MenuCategoryTest extends TestCase
{
    public function testCreateCategory(): void
    {
        $restaurantId = Uuid::v7();
        $category = MenuCategory::create($restaurantId, CategoryName::of('Starters'), 1);

        $this->assertInstanceOf(Uuid::class, $category->getId());
        $this->assertSame('Starters', $category->getName()->value());
        $this->assertTrue($category->isActive());
        $this->assertSame(1, $category->getDisplayOrder());
    }

    public function testDeactivateCategory(): void
    {
        $category = MenuCategory::create(Uuid::v7(), CategoryName::of('Mains'));
        $category->deactivate();

        $this->assertFalse($category->isActive());
    }

    public function testRenameCategory(): void
    {
        $category = MenuCategory::create(Uuid::v7(), CategoryName::of('Old Name'));
        $category->rename(CategoryName::of('New Name'));

        $this->assertSame('New Name', $category->getName()->value());
    }

    public function testCategoryNameCannotBeEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CategoryName::of('');
    }

    public function testCategoryNameMaxLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CategoryName::of(str_repeat('a', 151));
    }

    public function testCategoryNameEquality(): void
    {
        $a = CategoryName::of('Starters');
        $b = CategoryName::of('starters');
        $this->assertTrue($a->equals($b));
    }
}
