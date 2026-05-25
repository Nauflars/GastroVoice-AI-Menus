<?php

declare(strict_types=1);

namespace App\Tests\Unit\Menu\Domain\Entity;

use App\Menu\Domain\Entity\MenuItem;
use App\Menu\Domain\ValueObject\Price;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class MenuItemTest extends TestCase
{
    public function testCreateMenuItem(): void
    {
        $categoryId = Uuid::v7();
        $item = MenuItem::create($categoryId, 'Soup', 'Tomato soup', Price::of(8.50));

        $this->assertInstanceOf(Uuid::class, $item->getId());
        $this->assertSame('Soup', $item->getName());
        $this->assertSame('Tomato soup', $item->getDescription());
        $this->assertSame(8.50, $item->getPrice()->amount());
        $this->assertTrue($item->isAvailable());
    }

    public function testToggleAvailability(): void
    {
        $item = MenuItem::create(Uuid::v7(), 'Soup', null, Price::of(8.50));
        $item->toggleAvailability();

        $this->assertFalse($item->isAvailable());

        $item->toggleAvailability();
        $this->assertTrue($item->isAvailable());
    }

    public function testUpdateDetails(): void
    {
        $item = MenuItem::create(Uuid::v7(), 'Soup', null, Price::of(8.50));
        $item->updateDetails('Fish Soup', 'With cream', Price::of(12.00));

        $this->assertSame('Fish Soup', $item->getName());
        $this->assertSame('With cream', $item->getDescription());
        $this->assertSame(12.00, $item->getPrice()->amount());
    }

    public function testEmptyNameThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MenuItem::create(Uuid::v7(), '', null, Price::of(0));
    }

    public function testNegativePriceThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Price::of(-1.0);
    }

    public function testPriceEquality(): void
    {
        $a = Price::of(9.99, 'EUR');
        $b = Price::of(9.99, 'EUR');
        $c = Price::of(9.99, 'USD');
        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
