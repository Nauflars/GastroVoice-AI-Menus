<?php

declare(strict_types=1);

namespace App\Tests\Unit\Reservations\Domain\ValueObject;

use App\Reservations\Domain\ValueObject\ReservationStatus;
use PHPUnit\Framework\TestCase;

final class ReservationStatusTest extends TestCase
{
    public function testPendingToConfirmed(): void
    {
        $status = ReservationStatus::Pending;
        $next = $status->transitionTo(ReservationStatus::Confirmed);
        $this->assertSame(ReservationStatus::Confirmed, $next);
    }

    public function testPendingToCancelled(): void
    {
        $status = ReservationStatus::Pending;
        $next = $status->transitionTo(ReservationStatus::Cancelled);
        $this->assertSame(ReservationStatus::Cancelled, $next);
    }

    public function testConfirmedToCancelled(): void
    {
        $status = ReservationStatus::Confirmed;
        $next = $status->transitionTo(ReservationStatus::Cancelled);
        $this->assertSame(ReservationStatus::Cancelled, $next);
    }

    public function testConfirmedToNoShow(): void
    {
        $status = ReservationStatus::Confirmed;
        $next = $status->transitionTo(ReservationStatus::NoShow);
        $this->assertSame(ReservationStatus::NoShow, $next);
    }

    public function testCancelledIsTerminal(): void
    {
        $this->expectException(\DomainException::class);
        ReservationStatus::Cancelled->transitionTo(ReservationStatus::Confirmed);
    }

    public function testNoShowIsTerminal(): void
    {
        $this->expectException(\DomainException::class);
        ReservationStatus::NoShow->transitionTo(ReservationStatus::Pending);
    }

    public function testInvalidTransitionPendingToNoShow(): void
    {
        $this->expectException(\DomainException::class);
        ReservationStatus::Pending->transitionTo(ReservationStatus::NoShow);
    }

    public function testIsFinal(): void
    {
        $this->assertTrue(ReservationStatus::Cancelled->isFinal());
        $this->assertTrue(ReservationStatus::NoShow->isFinal());
        $this->assertFalse(ReservationStatus::Pending->isFinal());
        $this->assertFalse(ReservationStatus::Confirmed->isFinal());
    }
}
