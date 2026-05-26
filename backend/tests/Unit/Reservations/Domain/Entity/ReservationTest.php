<?php

declare(strict_types=1);

namespace App\Tests\Unit\Reservations\Domain\Entity;

use App\Reservations\Domain\Entity\Reservation;
use App\Reservations\Domain\ValueObject\ReservationStatus;
use App\Reservations\Domain\ValueObject\TimeSlot;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class ReservationTest extends TestCase
{
    private Uuid $restaurantId;

    protected function setUp(): void
    {
        $this->restaurantId = Uuid::v7();
    }

    public function testCreateReservationWithValidData(): void
    {
        $reservation = Reservation::create(
            $this->restaurantId,
            new \DateTimeImmutable('2025-12-01'),
            TimeSlot::fromString('13:00'),
            4,
            'John Doe',
            '+34600000001',
        );

        self::assertSame(ReservationStatus::Pending, $reservation->getStatus());
        self::assertSame(4, $reservation->getNumPeople());
        self::assertSame('John Doe', $reservation->getCustomerName());
        self::assertSame('13:00', $reservation->getTimeSlot()->toString());
    }

    public function testCreateWithZeroPeopleThrows(): void
    {
        $this->expectException(\DomainException::class);
        Reservation::create($this->restaurantId, new \DateTimeImmutable('2025-12-01'), TimeSlot::fromString('13:00'), 0, 'Test');
    }

    public function testConfirmTransition(): void
    {
        $r = Reservation::create($this->restaurantId, new \DateTimeImmutable('2025-12-01'), TimeSlot::fromString('13:00'), 2, 'Test');
        $r->confirm();
        self::assertSame(ReservationStatus::Confirmed, $r->getStatus());
    }

    public function testCancelTransition(): void
    {
        $r = Reservation::create($this->restaurantId, new \DateTimeImmutable('2025-12-01'), TimeSlot::fromString('13:00'), 2, 'Test');
        $r->cancel();
        self::assertSame(ReservationStatus::Cancelled, $r->getStatus());
    }

    public function testCannotCancelAlreadyCancelledReservation(): void
    {
        $r = Reservation::create($this->restaurantId, new \DateTimeImmutable('2025-12-01'), TimeSlot::fromString('13:00'), 2, 'Test');
        $r->cancel();
        $this->expectException(\DomainException::class);
        $r->cancel();
    }
}
