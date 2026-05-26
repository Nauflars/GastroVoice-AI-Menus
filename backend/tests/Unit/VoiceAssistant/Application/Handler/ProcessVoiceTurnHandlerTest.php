<?php

declare(strict_types=1);

namespace App\Tests\Unit\VoiceAssistant\Application\Handler;

use App\Menu\Application\Query\GetActiveMenuQuery;
use App\Shared\Domain\Bus\CommandBusInterface;
use App\Shared\Domain\Bus\QueryBusInterface;
use App\VoiceAssistant\Application\Command\ProcessVoiceTurnCommand;
use App\VoiceAssistant\Application\Command\ProcessVoiceTurnHandler;
use App\VoiceAssistant\Application\Command\ProcessVoiceTurnResult;
use App\VoiceAssistant\Application\Port\IntentDetectionResult;
use App\VoiceAssistant\Application\Port\IntentDetectorPort;
use App\VoiceAssistant\Domain\Entity\CallSession;
use App\VoiceAssistant\Domain\Repository\CallSessionRepositoryInterface;
use App\VoiceAssistant\Domain\ValueObject\Intent;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ProcessVoiceTurnHandlerTest extends TestCase
{
    private CallSessionRepositoryInterface&MockObject $sessions;
    private IntentDetectorPort&MockObject $intentDetector;
    private CommandBusInterface&MockObject $commandBus;
    private QueryBusInterface&MockObject $queryBus;
    private ProcessVoiceTurnHandler $handler;

    protected function setUp(): void
    {
        $this->sessions = $this->createMock(CallSessionRepositoryInterface::class);
        $this->intentDetector = $this->createMock(IntentDetectorPort::class);
        $this->commandBus = $this->createMock(CommandBusInterface::class);
        $this->queryBus = $this->createMock(QueryBusInterface::class);

        $this->handler = new ProcessVoiceTurnHandler(
            $this->sessions,
            $this->intentDetector,
            $this->commandBus,
            $this->queryBus,
        );
    }

    public function testCreatesNewSessionIfNoneProvided(): void
    {
        $this->queryBus->method('ask')->willReturn([]);
        $this->intentDetector->method('detect')->willReturn(
            new IntentDetectionResult(Intent::Unknown, [], [], 'Hola, ¿en qué puedo ayudarle?'),
        );
        $this->sessions->expects($this->once())->method('save');

        $result = ($this->handler)(new ProcessVoiceTurnCommand(
            restaurantId: (string) Uuid::v7(),
            userText: 'Hola',
        ));

        $this->assertInstanceOf(ProcessVoiceTurnResult::class, $result);
        $this->assertSame('unknown', $result->intent);
        $this->assertNotEmpty($result->sessionId);
    }

    public function testLoadsExistingSession(): void
    {
        $session = CallSession::start((string) Uuid::v7(), 'caller-1');

        $this->sessions->method('findById')->willReturn($session);
        $this->queryBus->method('ask')->willReturn([]);
        $this->intentDetector->method('detect')->willReturn(
            new IntentDetectionResult(Intent::QueryMenu, [], ['numPeople'], '¿Cuántas personas serán?'),
        );
        $this->sessions->expects($this->once())->method('save');

        $result = ($this->handler)(new ProcessVoiceTurnCommand(
            restaurantId: (string) Uuid::v7(),
            userText: 'Quiero ver el menú',
            sessionId: (string) $session->getId(),
        ));

        $this->assertSame((string) $session->getId(), $result->sessionId);
    }

    public function testExecutesReservationIntent(): void
    {
        $this->queryBus->method('ask')->willReturn([]);
        $this->intentDetector->method('detect')->willReturn(
            new IntentDetectionResult(
                Intent::CreateReservation,
                ['date' => '2026-06-15', 'timeSlot' => '13:00', 'numPeople' => 4, 'customerName' => 'Juan'],
                [], // no missing fields → action is executed
                'Voy a reservar su mesa.',
            ),
        );
        $this->commandBus->expects($this->once())->method('dispatch');
        $this->sessions->expects($this->once())->method('save');

        $result = ($this->handler)(new ProcessVoiceTurnCommand(
            restaurantId: (string) Uuid::v7(),
            userText: 'Mesa para 4 el 15 de junio a las 13',
        ));

        $this->assertSame('create_reservation', $result->intent);
        $this->assertStringContainsString('reservado', $result->replyText);
    }

    public function testDoesNotExecuteIntentWhenMissingFields(): void
    {
        $this->queryBus->method('ask')->willReturn([]);
        $this->intentDetector->method('detect')->willReturn(
            new IntentDetectionResult(
                Intent::CreateReservation,
                ['date' => '2026-06-15'],
                ['timeSlot', 'numPeople'],
                '¿A qué hora y para cuántas personas?',
            ),
        );
        $this->commandBus->expects($this->never())->method('dispatch');

        $result = ($this->handler)(new ProcessVoiceTurnCommand(
            restaurantId: (string) Uuid::v7(),
            userText: 'Quiero reservar el 15 de junio',
        ));

        $this->assertSame('¿A qué hora y para cuántas personas?', $result->replyText);
    }

    public function testExecutesOrderIntentWithMenuLookup(): void
    {
        $menu = [[
            'name' => 'Entrantes',
            'items' => [
                ['id' => 'item-1', 'name' => 'Paella', 'price' => '15.50'],
            ],
        ]];

        $this->queryBus->method('ask')->willReturn($menu);
        $this->intentDetector->method('detect')->willReturn(
            new IntentDetectionResult(
                Intent::CreateOrder,
                ['lines' => [['menuItemName' => 'Paella', 'quantity' => 2]]],
                [],
                'Procesando su pedido.',
            ),
        );
        $this->commandBus->expects($this->once())->method('dispatch');

        $result = ($this->handler)(new ProcessVoiceTurnCommand(
            restaurantId: (string) Uuid::v7(),
            userText: 'Quiero 2 paellas',
        ));

        $this->assertSame('create_order', $result->intent);
        $this->assertStringContainsString('pedido', $result->replyText);
    }
}
