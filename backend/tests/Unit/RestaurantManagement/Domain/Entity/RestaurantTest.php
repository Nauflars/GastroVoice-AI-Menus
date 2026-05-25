<?php

declare(strict_types=1);

namespace App\Tests\Unit\RestaurantManagement\Domain\Entity;

use App\RestaurantManagement\Domain\Entity\OpeningHour;
use App\RestaurantManagement\Domain\Entity\Restaurant;
use App\RestaurantManagement\Domain\ValueObject\SeatCapacity;
use App\RestaurantManagement\Domain\ValueObject\SlotDuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class RestaurantTest extends TestCase
{
    private function makeRestaurant(): Restaurant
    {
        return new Restaurant(
            id: Uuid::v7(),
            name: 'La Bonne Table',
            address: '123 Rue de Paris',
            phone: '+33123456789',
            seatCapacity: new SeatCapacity(50),
            slotDuration: new SlotDuration(30),
            timezone: 'Europe/Paris',
        );
    }

    public function testRestaurantIsCreatedWithCorrectValues(): void
    {
        $r = $this->makeRestaurant();

        self::assertSame('La Bonne Table', $r->getName());
        self::assertSame('123 Rue de Paris', $r->getAddress());
        self::assertSame(50, $r->getSeatCapacity()->value());
        self::assertSame(30, $r->getSlotDuration()->minutes());
        self::assertSame('Europe/Paris', $r->getTimezone());
        self::assertEmpty($r->getOpeningHours());
    }

    public function testUpdateChangesValues(): void
    {
        $r = $this->makeRestaurant();
        $before = $r->getUpdatedAt();

        $r->update(
            name: 'Le Nouveau',
            address: '456 Avenue',
            phone: '+33987654321',
            seatCapacity: new SeatCapacity(80),
            slotDuration: new SlotDuration(45),
            timezone: 'UTC',
        );

        self::assertSame('Le Nouveau', $r->getName());
        self::assertSame(80, $r->getSeatCapacity()->value());
        self::assertSame(45, $r->getSlotDuration()->minutes());
        self::assertGreaterThanOrEqual($before->getTimestamp(), $r->getUpdatedAt()->getTimestamp());
    }

    public function testSetOpeningHoursReplacesExisting(): void
    {
        $r = $this->makeRestaurant();
        $oh = new OpeningHour(Uuid::v7(), $r->getId(), 0, false, '09:00', '22:00');

        $r->setOpeningHours([$oh]);

        self::assertCount(1, $r->getOpeningHours());
    }

    public function testOpeningHourInvalidDayThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new OpeningHour(Uuid::v7(), Uuid::v7(), 7, false, '09:00', '22:00');
    }

    public function testOpeningHourOpenWithoutTimesThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new OpeningHour(Uuid::v7(), Uuid::v7(), 0, false, null, null);
    }
}
