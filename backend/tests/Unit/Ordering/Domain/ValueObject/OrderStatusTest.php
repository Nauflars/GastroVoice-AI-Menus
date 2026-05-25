<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ordering\Domain\ValueObject;

use App\Ordering\Domain\ValueObject\OrderStatus;
use PHPUnit\Framework\TestCase;

class OrderStatusTest extends TestCase
{
    public function testValidTransitionPendingToConfirmed(): void
    {
        $result = OrderStatus::Pending->transitionTo(OrderStatus::Confirmed);
        self::assertSame(OrderStatus::Confirmed, $result);
    }

    public function testValidTransitionConfirmedToPreparing(): void
    {
        $result = OrderStatus::Confirmed->transitionTo(OrderStatus::Preparing);
        self::assertSame(OrderStatus::Preparing, $result);
    }

    public function testInvalidTransitionPendingToDelivered(): void
    {
        $this->expectException(\DomainException::class);
        OrderStatus::Pending->transitionTo(OrderStatus::Delivered);
    }

    public function testCanCancelPendingOrder(): void
    {
        $result = OrderStatus::Pending->transitionTo(OrderStatus::Cancelled);
        self::assertSame(OrderStatus::Cancelled, $result);
    }

    public function testFinalStatusIsFinal(): void
    {
        self::assertTrue(OrderStatus::Delivered->isFinal());
        self::assertTrue(OrderStatus::Cancelled->isFinal());
    }

    public function testNonFinalStatusIsNotFinal(): void
    {
        self::assertFalse(OrderStatus::Pending->isFinal());
        self::assertFalse(OrderStatus::Preparing->isFinal());
    }
}
