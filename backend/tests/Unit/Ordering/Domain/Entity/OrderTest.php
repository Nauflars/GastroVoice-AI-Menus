<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ordering\Domain\Entity;

use App\Ordering\Domain\Entity\Order;
use App\Ordering\Domain\ValueObject\OrderSource;
use App\Ordering\Domain\ValueObject\OrderStatus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class OrderTest extends TestCase
{
    private Uuid $restaurantId;

    protected function setUp(): void
    {
        $this->restaurantId = Uuid::v7();
    }

    public function testPlaceCreatesOrderWithPendingStatus(): void
    {
        $order = Order::place($this->restaurantId, OrderSource::Phone, null, '+34600000001');

        self::assertSame(OrderStatus::Pending, $order->getStatus());
        self::assertSame(OrderSource::Phone, $order->getSource());
        self::assertSame('+34600000001', $order->getCustomerPhone());
        self::assertEmpty($order->getLines());
    }

    public function testAddLineCalculatesTotal(): void
    {
        $order = Order::place($this->restaurantId, OrderSource::Web, '5', null);
        $itemId = Uuid::v7();

        $order->addLine($itemId, 'Paella', 2, 12.50, 'EUR');
        $order->addLine($itemId, 'Sangria', 1, 4.00, 'EUR');

        self::assertSame(29.0, $order->getTotal());
        self::assertCount(2, $order->getLines());
    }

    public function testUpdateStatusTransitions(): void
    {
        $order = Order::place($this->restaurantId, OrderSource::Manual, null, null);
        $order->updateStatus(OrderStatus::Confirmed);
        self::assertSame(OrderStatus::Confirmed, $order->getStatus());
    }

    public function testInvalidStatusTransitionThrows(): void
    {
        $order = Order::place($this->restaurantId, OrderSource::Manual, null, null);

        $this->expectException(\DomainException::class);
        $order->updateStatus(OrderStatus::Delivered);
    }

    public function testGetCurrencyFromFirstLine(): void
    {
        $order = Order::place($this->restaurantId, OrderSource::Manual, null, null);
        $order->addLine(Uuid::v7(), 'Tapas', 3, 5.00, 'EUR');

        self::assertSame('EUR', $order->getCurrency());
    }

    public function testEmptyOrderReturnsDefaultCurrency(): void
    {
        $order = Order::place($this->restaurantId, OrderSource::Manual, null, null);
        self::assertSame('EUR', $order->getCurrency());
        self::assertSame(0.0, $order->getTotal());
    }
}
