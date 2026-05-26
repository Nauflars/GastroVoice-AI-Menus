<?php

declare(strict_types=1);

namespace App\Tests\Unit\Reservations\Domain\ValueObject;

use App\Reservations\Domain\ValueObject\TimeSlot;
use PHPUnit\Framework\TestCase;

final class TimeSlotTest extends TestCase
{
    public function testValidTimeSlot(): void
    {
        $slot = TimeSlot::fromString('13:30');
        $this->assertSame('13:30', $slot->toString());
    }

    public function testInvalidFormatThrows(): void
    {
        $this->expectException(\DomainException::class);
        TimeSlot::fromString('25:00');
    }

    public function testInvalidFormatStringThrows(): void
    {
        $this->expectException(\DomainException::class);
        TimeSlot::fromString('abc');
    }

    public function testFromHourString(): void
    {
        $slot = TimeSlot::fromHourString('14:00');
        $this->assertSame('14:00', $slot->toString());
    }

    public function testFromHourStringRejectsMinutes(): void
    {
        $this->expectException(\DomainException::class);
        TimeSlot::fromHourString('14:30');
    }

    public function testFromHour(): void
    {
        $slot = TimeSlot::fromHour(20);
        $this->assertSame('20:00', $slot->toString());
    }

    public function testFromHourInvalidThrows(): void
    {
        $this->expectException(\DomainException::class);
        TimeSlot::fromHour(25);
    }

    public function testAlignToGrid(): void
    {
        $slot = TimeSlot::fromString('13:45');
        $aligned = $slot->alignToGrid(60);
        $this->assertSame('13:00', $aligned->toString());
    }

    public function testAlignToGrid30Min(): void
    {
        $slot = TimeSlot::fromString('13:45');
        $aligned = $slot->alignToGrid(30);
        $this->assertSame('13:30', $aligned->toString());
    }

    public function testEquals(): void
    {
        $a = TimeSlot::fromString('13:00');
        $b = TimeSlot::fromString('13:00');
        $c = TimeSlot::fromString('14:00');

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
