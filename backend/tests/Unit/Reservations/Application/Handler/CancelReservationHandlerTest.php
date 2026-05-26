<?php

declare(strict_types=1);

namespace App\Tests\Unit\Reservations\Application\Handler;

use App\Reservations\Application\Command\CancelReservationCommand;
use App\Reservations\Application\Command\CancelReservationHandler;
use App\Reservations\Domain\Entity\Reservation;
use App\Reservations\Domain\Repository\ReservationRepositoryInterface;
use App\Reservations\Domain\ValueObject\ReservationStatus;
use App\Reservations\Domain\ValueObject\TimeSlot;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class CancelReservationHandlerTest extends TestCase
{
    private ReservationRepositoryInterface&MockObject $repo;

    protected function setUp(): void
    {
        $this->repo = $this->createMock(ReservationRepositoryInterface::class);
    }

    public function testCancelsReservation(): void
    {
        $reservation = Reservation::create(
            Uuid::v7(),
            new \DateTimeImmutable('2026-06-15'),
            TimeSlot::fromString('13:00'),
            4,
            'Juan García',
            '+34600111222',
        );

        $this->repo->method('findById')->willReturn($reservation);
        $this->repo->expects($this->once())->method('save');

        $handler = new CancelReservationHandler($this->repo);
        $handler(new CancelReservationCommand((string) $reservation->getId()));

        $this->assertSame(ReservationStatus::Cancelled, $reservation->getStatus());
    }

    public function testReservationNotFoundThrows(): void
    {
        $this->repo->method('findById')->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Reservation not found');

        $handler = new CancelReservationHandler($this->repo);
        $handler(new CancelReservationCommand((string) Uuid::v7()));
    }
}
