<?php

declare(strict_types=1);

namespace App\Tests\Unit\Reservations\Domain\Service;

use App\Reservations\Domain\Repository\ReservationRepositoryInterface;
use App\Reservations\Domain\Service\ReservationAvailabilityChecker;
use App\Reservations\Domain\ValueObject\TimeSlot;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ReservationAvailabilityCheckerTest extends TestCase
{
    private ReservationRepositoryInterface&MockObject $repo;
    private ReservationAvailabilityChecker $checker;

    protected function setUp(): void
    {
        $this->repo = $this->createMock(ReservationRepositoryInterface::class);
        $this->checker = new ReservationAvailabilityChecker($this->repo);
    }

    public function testIsAvailableWhenTablesAreFree(): void
    {
        $this->repo->method('countTablesForSlot')->willReturn(5);

        $result = $this->checker->isAvailable(
            Uuid::v7(),
            new \DateTimeImmutable('2026-06-01'),
            TimeSlot::fromString('13:00'),
        );

        $this->assertTrue($result);
    }

    public function testIsNotAvailableWhenAllTablesTaken(): void
    {
        $this->repo->method('countTablesForSlot')->willReturn(10);

        $result = $this->checker->isAvailable(
            Uuid::v7(),
            new \DateTimeImmutable('2026-06-01'),
            TimeSlot::fromString('13:00'),
        );

        $this->assertFalse($result);
    }

    public function testGetAvailableTablesReturnsCorrectCount(): void
    {
        $this->repo->method('countTablesForSlot')->willReturn(7);

        $available = $this->checker->getAvailableTables(
            Uuid::v7(),
            new \DateTimeImmutable('2026-06-01'),
            TimeSlot::fromString('20:00'),
        );

        $this->assertSame(3, $available);
    }

    public function testGetAvailableTablesNeverNegative(): void
    {
        $this->repo->method('countTablesForSlot')->willReturn(15);

        $available = $this->checker->getAvailableTables(
            Uuid::v7(),
            new \DateTimeImmutable('2026-06-01'),
            TimeSlot::fromString('20:00'),
        );

        $this->assertSame(0, $available);
    }

    public function testExactCapacityIsNotAvailable(): void
    {
        $this->repo->method('countTablesForSlot')->willReturn(ReservationAvailabilityChecker::MAX_TABLES);

        $this->assertFalse($this->checker->isAvailable(
            Uuid::v7(),
            new \DateTimeImmutable('2026-06-01'),
            TimeSlot::fromString('21:00'),
        ));
    }
}
