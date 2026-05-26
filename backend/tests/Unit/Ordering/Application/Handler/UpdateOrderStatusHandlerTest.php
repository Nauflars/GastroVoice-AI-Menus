<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ordering\Application\Handler;

use App\Ordering\Application\Command\UpdateOrderStatusCommand;
use App\Ordering\Application\Command\UpdateOrderStatusHandler;
use App\Ordering\Domain\Entity\Order;
use App\Ordering\Domain\Repository\OrderRepositoryInterface;
use App\Ordering\Domain\ValueObject\OrderSource;
use App\Ordering\Domain\ValueObject\OrderStatus;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class UpdateOrderStatusHandlerTest extends TestCase
{
    private OrderRepositoryInterface&MockObject $orders;

    protected function setUp(): void
    {
        $this->orders = $this->createMock(OrderRepositoryInterface::class);
    }

    public function testUpdateStatusTransitions(): void
    {
        $order = Order::place(Uuid::v7(), OrderSource::Web, '3', null);
        $order->addLine(Uuid::v7(), 'Soup', 1, 5.00, 'EUR');

        $this->orders->method('findById')->willReturn($order);
        $this->orders->expects($this->once())->method('save');

        $handler = new UpdateOrderStatusHandler($this->orders);
        $handler(new UpdateOrderStatusCommand((string) $order->getId(), 'confirmed'));

        $this->assertSame(OrderStatus::Confirmed, $order->getStatus());
    }

    public function testInvalidTransitionThrows(): void
    {
        $order = Order::place(Uuid::v7(), OrderSource::Web, null, null);
        $order->addLine(Uuid::v7(), 'Pasta', 1, 10.00, 'EUR');

        $this->orders->method('findById')->willReturn($order);

        $this->expectException(\DomainException::class);

        $handler = new UpdateOrderStatusHandler($this->orders);
        // pending → delivered is not allowed
        $handler(new UpdateOrderStatusCommand((string) $order->getId(), 'delivered'));
    }

    public function testOrderNotFoundThrows(): void
    {
        $this->orders->method('findById')->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Order not found');

        $handler = new UpdateOrderStatusHandler($this->orders);
        $handler(new UpdateOrderStatusCommand('a1b2c3d4-0000-7000-8000-000000000099', 'confirmed'));
    }
}
