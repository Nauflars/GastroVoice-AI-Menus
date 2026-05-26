<?php

declare(strict_types=1);

namespace App\Tests\Unit\Reservations\Application\Handler;

use App\Reservations\Application\Command\CreateReservationCommand;
use App\Reservations\Application\Command\CreateReservationHandler;
use App\Reservations\Domain\Entity\Reservation;
use App\Reservations\Domain\Exception\SlotFullException;
use App\Reservations\Domain\Repository\ReservationRepositoryInterface;
use App\Reservations\Domain\Service\ReservationAvailabilityChecker;
use App\Reservations\Domain\ValueObject\TimeSlot;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class CreateReservationHandlerTest extends TestCase
{
    private ReservationRepositoryInterface&MockObject $repo;
    private ReservationAvailabilityChecker&MockObject $checker;

    protected function setUp(): void
    {
        $this->repo = $this->createMock(ReservationRepositoryInterface::class);
        $this->checker = $this->createMock(ReservationAvailabilityChecker::class);
    }

    public function testCreatesReservationWhenAvailable(): void
    {
        $this->checker->method('isAvailable')->willReturn(true);
        $this->repo->expects($this->once())->method('save');

        $handler = new CreateReservationHandler($this->repo, $this->checker);

        $reservation = $handler(new CreateReservationCommand(
            restaurantId: (string) Uuid::v7(),
            date: '2026-06-15',
            timeSlot: '13:00',
            numPeople: 4,
            customerName: 'Juan García',
            customerPhone: '+34600111222',
        ));

        $this->assertInstanceOf(Reservation::class, $reservation);
    }

    public function testThrowsWhenSlotFull(): void
    {
        $this->checker->method('isAvailable')->willReturn(false);

        $this->expectException(SlotFullException::class);

        $handler = new CreateReservationHandler($this->repo, $this->checker);
        $handler(new CreateReservationCommand(
            restaurantId: (string) Uuid::v7(),
            date: '2026-06-15',
            timeSlot: '13:00',
            numPeople: 4,
            customerName: 'María López',
        ));
    }
}
