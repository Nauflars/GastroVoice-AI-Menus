<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ordering\Application\Handler;

use App\Ordering\Application\Command\PlaceOrderCommand;
use App\Ordering\Application\Command\PlaceOrderHandler;
use App\Ordering\Application\Command\PlaceOrderLineDTO;
use App\Ordering\Domain\Entity\Order;
use App\Ordering\Domain\Repository\OrderRepositoryInterface;
use App\Ordering\Domain\ValueObject\OrderStatus;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PlaceOrderHandlerTest extends TestCase
{
    private OrderRepositoryInterface&MockObject $orders;

    protected function setUp(): void
    {
        $this->orders = $this->createMock(OrderRepositoryInterface::class);
    }

    public function testPlaceOrderCreatesOrderWithLines(): void
    {
        $this->orders->expects($this->once())->method('save');

        $handler = new PlaceOrderHandler($this->orders);

        $command = new PlaceOrderCommand(
            restaurantId: 'a1b2c3d4-0000-7000-8000-000000000001',
            source: 'web',
            tableNumber: '5',
            customerPhone: '+34600000000',
            lines: [
                new PlaceOrderLineDTO('b1b2c3d4-0000-7000-8000-000000000001', 'Paella', 2, 15.50),
                new PlaceOrderLineDTO('b1b2c3d4-0000-7000-8000-000000000002', 'Sangria', 1, 8.00),
            ],
        );

        $order = $handler($command);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertSame(OrderStatus::Pending, $order->getStatus());
        $this->assertCount(2, $order->getLines());
        $this->assertEquals(39.00, $order->getTotal());
    }

    public function testPlaceOrderWithNoLinesThrows(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('at least one line');

        $handler = new PlaceOrderHandler($this->orders);
        $handler(new PlaceOrderCommand(
            restaurantId: 'a1b2c3d4-0000-7000-8000-000000000001',
            source: 'web',
            tableNumber: null,
            customerPhone: null,
            lines: [],
        ));
    }
}
