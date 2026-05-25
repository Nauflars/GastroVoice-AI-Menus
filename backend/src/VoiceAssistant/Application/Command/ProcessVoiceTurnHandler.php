<?php

declare(strict_types=1);

namespace App\VoiceAssistant\Application\Command;

use App\Ordering\Application\Command\PlaceOrderCommand;
use App\Ordering\Application\Command\PlaceOrderLineDTO;
use App\Reservations\Application\Command\CreateReservationCommand;
use App\Reservations\Domain\Exception\SlotFullException;
use App\Shared\Domain\Bus\CommandBusInterface;
use App\VoiceAssistant\Application\Port\IntentDetectorPort;
use App\VoiceAssistant\Domain\Entity\CallSession;
use App\VoiceAssistant\Domain\Repository\CallSessionRepositoryInterface;
use App\VoiceAssistant\Domain\ValueObject\ConversationTurn;
use App\VoiceAssistant\Domain\ValueObject\Intent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

final class ProcessVoiceTurnResult
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $replyText,
        public readonly string $intent,
    ) {}
}

#[AsMessageHandler]
final class ProcessVoiceTurnHandler
{
    public function __construct(
        private CallSessionRepositoryInterface $sessions,
        private IntentDetectorPort $intentDetector,
        private CommandBusInterface $commandBus,
    ) {}

    public function __invoke(ProcessVoiceTurnCommand $command): ProcessVoiceTurnResult
    {
        // Load or create session
        $session = null;
        if ($command->sessionId !== null) {
            $session = $this->sessions->findById(Uuid::fromString($command->sessionId));
        }
        if ($session === null) {
            $session = CallSession::start($command->restaurantId, $command->callerId);
        }

        // Add user turn
        $session->addTurn(ConversationTurn::user($command->userText));

        // Detect intent
        $result = $this->intentDetector->detect(
            $session->getHistoryAsMessages(),
            $command->restaurantContext,
        );

        // Merge pending data
        if (!empty($result->data)) {
            $session->updatePendingData($result->data);
        }

        $replyText = $result->reply;

        // Execute action if intent is complete
        if (empty($result->missingFields)) {
            $replyText = $this->executeIntent($result->intent, $session, $result, $command->restaurantId, $replyText);
        }

        // Add assistant turn
        $session->addTurn(ConversationTurn::assistant($replyText));
        $this->sessions->save($session);

        return new ProcessVoiceTurnResult(
            (string) $session->getId(),
            $replyText,
            $result->intent->value,
        );
    }

    private function executeIntent(Intent $intent, CallSession $session, $result, string $restaurantId, string $defaultReply): string
    {
        $data = $session->getPendingData();

        try {
            switch ($intent) {
                case Intent::CreateReservation:
                    $this->commandBus->dispatch(new CreateReservationCommand(
                        $restaurantId,
                        $data['date'] ?? date('Y-m-d'),
                        $data['timeSlot'] ?? '13:00',
                        (int) ($data['numPeople'] ?? 1),
                        $data['customerName'] ?? 'Guest',
                        50, // default capacity — should come from restaurant context
                        $data['customerPhone'] ?? null,
                        $data['customerEmail'] ?? null,
                        $data['notes'] ?? null,
                    ));
                    $session->clearPendingData();
                    return sprintf(
                        'Perfecto, he reservado una mesa para %d personas el %s a las %s. ¿Hay algo más en lo que pueda ayudarle?',
                        $data['numPeople'] ?? 1,
                        $data['date'] ?? '',
                        $data['timeSlot'] ?? '',
                    );

                case Intent::CreateOrder:
                    $lines = array_map(fn(array $l) => new PlaceOrderLineDTO(
                        $l['menuItemId'] ?? Uuid::v7()->toString(),
                        $l['menuItemName'] ?? 'Unknown item',
                        (int) ($l['quantity'] ?? 1),
                        (float) ($l['unitPrice'] ?? 0.0),
                        $l['currency'] ?? 'EUR',
                    ), $data['lines'] ?? []);

                    $this->commandBus->dispatch(new PlaceOrderCommand(
                        $restaurantId,
                        'phone',
                        $data['tableNumber'] ?? null,
                        $session->getCallerId(),
                        $lines,
                        $data['notes'] ?? null,
                    ));
                    $session->clearPendingData();
                    return 'Su pedido ha sido registrado. Estará listo en breve. ¡Hasta luego!';

                default:
                    return $defaultReply;
            }
        } catch (SlotFullException $e) {
            return 'Lo siento, no hay disponibilidad para ese horario. ¿Le gustaría intentar con otro horario o fecha?';
        } catch (\Throwable $e) {
            return 'Ha ocurrido un error al procesar su solicitud. ¿Podría repetirlo?';
        }
    }
}
