<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ordering\Domain\Entity;

use App\Ordering\Domain\Entity\OrderLine;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class OrderLineTest extends TestCase
{
    public function testCreateOrderLine(): void
    {
        $line = new OrderLine(
            Uuid::v7(),
            Uuid::v7(),
            Uuid::v7(),
            'Paella Valenciana',
            2,
            15.50,
            'EUR',
        );

        $this->assertSame('Paella Valenciana', $line->getMenuItemName());
        $this->assertSame(2, $line->getQuantity());
        $this->assertSame(15.50, $line->getUnitPrice());
        $this->assertEquals(31.00, $line->getLineTotal());
        $this->assertSame('EUR', $line->getCurrency());
    }

    public function testQuantityMustBeAtLeastOne(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity must be at least 1');

        new OrderLine(
            Uuid::v7(),
            Uuid::v7(),
            Uuid::v7(),
            'Invalid Item',
            0,
            10.00,
            'EUR',
        );
    }

    public function testLineTotalCalculation(): void
    {
        $line = new OrderLine(
            Uuid::v7(),
            Uuid::v7(),
            Uuid::v7(),
            'Wine',
            3,
            12.99,
            'EUR',
        );

        $this->assertEquals(38.97, $line->getLineTotal());
    }
}
