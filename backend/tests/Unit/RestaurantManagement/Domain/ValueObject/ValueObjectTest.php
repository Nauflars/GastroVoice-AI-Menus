<?php

declare(strict_types=1);

namespace App\Tests\Unit\RestaurantManagement\Domain\ValueObject;

use App\RestaurantManagement\Domain\ValueObject\SeatCapacity;
use App\RestaurantManagement\Domain\ValueObject\SlotDuration;
use PHPUnit\Framework\TestCase;

final class ValueObjectTest extends TestCase
{
    // SeatCapacity tests
    public function testSeatCapacityAcceptsValidValue(): void
    {
        $sc = new SeatCapacity(100);
        self::assertSame(100, $sc->value());
    }

    public function testSeatCapacityRejectsZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SeatCapacity(0);
    }

    public function testSeatCapacityRejectsNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SeatCapacity(-5);
    }

    public function testSeatCapacityEquality(): void
    {
        self::assertTrue((new SeatCapacity(50))->equals(new SeatCapacity(50)));
        self::assertFalse((new SeatCapacity(50))->equals(new SeatCapacity(51)));
    }

    // SlotDuration tests
    public function testSlotDurationAcceptsValidValue(): void
    {
        $sd = new SlotDuration(30);
        self::assertSame(30, $sd->minutes());
    }

    public function testSlotDurationRejectsLessThan15(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SlotDuration(10);
    }

    public function testSlotDurationRejectsNonMultipleOf5(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SlotDuration(17);
    }

    public function testSlotDurationEquality(): void
    {
        self::assertTrue((new SlotDuration(30))->equals(new SlotDuration(30)));
        self::assertFalse((new SlotDuration(30))->equals(new SlotDuration(45)));
    }
}
