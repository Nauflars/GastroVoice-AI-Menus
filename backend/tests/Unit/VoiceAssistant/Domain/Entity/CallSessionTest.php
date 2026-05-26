<?php

declare(strict_types=1);

namespace App\Tests\Unit\VoiceAssistant\Domain\Entity;

use App\VoiceAssistant\Domain\Entity\CallSession;
use App\VoiceAssistant\Domain\ValueObject\ConversationTurn;
use PHPUnit\Framework\TestCase;

class CallSessionTest extends TestCase
{
    public function testStartCreatesActiveSession(): void
    {
        $session = CallSession::start('restaurant-123', '+34600000001');
        self::assertSame('active', $session->getState());
        self::assertEmpty($session->getHistory());
    }

    public function testAddTurnAccumulatesHistory(): void
    {
        $session = CallSession::start('restaurant-123');
        $session->addTurn(ConversationTurn::user('Hello'));
        $session->addTurn(ConversationTurn::assistant('How can I help?'));

        self::assertCount(2, $session->getHistory());
    }

    public function testUpdatePendingDataMerges(): void
    {
        $session = CallSession::start('restaurant-123');
        $session->updatePendingData(['date' => '2025-12-01']);
        $session->updatePendingData(['numPeople' => 4]);

        self::assertSame(['date' => '2025-12-01', 'numPeople' => 4], $session->getPendingData());
    }

    public function testCompleteChangesState(): void
    {
        $session = CallSession::start('restaurant-123');
        $session->complete();
        self::assertSame('completed', $session->getState());
        self::assertNotNull($session->getEndedAt());
    }

    public function testAbandonChangesState(): void
    {
        $session = CallSession::start('restaurant-123');
        $session->abandon();
        self::assertSame('abandoned', $session->getState());
    }

    public function testGetHistoryAsMessages(): void
    {
        $session = CallSession::start('restaurant-123');
        $session->addTurn(ConversationTurn::user('I want a table for 2'));
        $session->addTurn(ConversationTurn::assistant('For when?'));

        $messages = $session->getHistoryAsMessages();
        self::assertSame('user', $messages[0]['role']);
        self::assertSame('assistant', $messages[1]['role']);
    }
}
