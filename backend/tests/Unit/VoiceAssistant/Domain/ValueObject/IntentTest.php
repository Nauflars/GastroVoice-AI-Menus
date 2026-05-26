<?php

declare(strict_types=1);

namespace App\Tests\Unit\VoiceAssistant\Domain\ValueObject;

use App\VoiceAssistant\Domain\ValueObject\Intent;
use PHPUnit\Framework\TestCase;

final class IntentTest extends TestCase
{
    public function testAllIntentsHaveCorrectValues(): void
    {
        $this->assertSame('create_reservation', Intent::CreateReservation->value);
        $this->assertSame('check_availability', Intent::CheckAvailability->value);
        $this->assertSame('create_order', Intent::CreateOrder->value);
        $this->assertSame('query_menu', Intent::QueryMenu->value);
        $this->assertSame('unknown', Intent::Unknown->value);
    }

    public function testFromValidString(): void
    {
        $this->assertSame(Intent::CreateReservation, Intent::from('create_reservation'));
        $this->assertSame(Intent::Unknown, Intent::from('unknown'));
    }

    public function testTryFromInvalidReturnsNull(): void
    {
        $this->assertNull(Intent::tryFrom('invalid_intent'));
    }

    public function testIntentCount(): void
    {
        $this->assertCount(5, Intent::cases());
    }
}
