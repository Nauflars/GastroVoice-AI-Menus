<?php

declare(strict_types=1);

namespace App\Tests\Unit\VoiceAssistant\Domain\ValueObject;

use App\VoiceAssistant\Domain\ValueObject\ConversationTurn;
use PHPUnit\Framework\TestCase;

final class ConversationTurnTest extends TestCase
{
    public function testUserFactory(): void
    {
        $turn = ConversationTurn::user('Quiero reservar una mesa');
        $this->assertSame('user', $turn->role);
        $this->assertSame('Quiero reservar una mesa', $turn->content);
        $this->assertInstanceOf(\DateTimeImmutable::class, $turn->timestamp);
    }

    public function testAssistantFactory(): void
    {
        $turn = ConversationTurn::assistant('¡Claro! ¿Para cuántas personas?');
        $this->assertSame('assistant', $turn->role);
        $this->assertSame('¡Claro! ¿Para cuántas personas?', $turn->content);
    }

    public function testToArray(): void
    {
        $turn = ConversationTurn::user('Hola');
        $arr = $turn->toArray();

        $this->assertArrayHasKey('role', $arr);
        $this->assertArrayHasKey('content', $arr);
        $this->assertArrayHasKey('timestamp', $arr);
        $this->assertSame('user', $arr['role']);
        $this->assertSame('Hola', $arr['content']);
        // ATOM format check
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $arr['timestamp']);
    }
}
