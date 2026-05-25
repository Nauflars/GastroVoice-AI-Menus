<?php

declare(strict_types=1);

namespace App\VoiceAssistant\Domain\Entity;

use App\VoiceAssistant\Domain\ValueObject\ConversationTurn;
use Symfony\Component\Uid\Uuid;

class CallSession
{
    private \DateTimeImmutable $startedAt;
    private ?\DateTimeImmutable $endedAt = null;
    private string $state = 'active'; // active | completed | abandoned

    /** @var ConversationTurn[] */
    private array $history = [];

    /** @var array<string, mixed> */
    private array $pendingData = [];

    private function __construct(
        private Uuid $id,
        private string $restaurantId,
        private string $callerId,
    ) {
        $this->startedAt = new \DateTimeImmutable();
    }

    public static function start(string $restaurantId, string $callerId = 'unknown'): self
    {
        return new self(Uuid::v7(), $restaurantId, $callerId);
    }

    public function addTurn(ConversationTurn $turn): void
    {
        $this->history[] = $turn;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updatePendingData(array $data): void
    {
        $this->pendingData = array_merge($this->pendingData, $data);
    }

    public function clearPendingData(): void
    {
        $this->pendingData = [];
    }

    public function complete(): void
    {
        $this->state = 'completed';
        $this->endedAt = new \DateTimeImmutable();
    }

    public function abandon(): void
    {
        $this->state = 'abandoned';
        $this->endedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }
    public function getRestaurantId(): string { return $this->restaurantId; }
    public function getCallerId(): string { return $this->callerId; }
    public function getState(): string { return $this->state; }
    public function getStartedAt(): \DateTimeImmutable { return $this->startedAt; }
    public function getEndedAt(): ?\DateTimeImmutable { return $this->endedAt; }

    /** @return ConversationTurn[] */
    public function getHistory(): array { return $this->history; }

    /** @return array<string, mixed> */
    public function getPendingData(): array { return $this->pendingData; }

    public function getHistoryAsMessages(): array
    {
        return array_map(fn(ConversationTurn $t) => [
            'role'    => $t->role,
            'content' => $t->content,
        ], $this->history);
    }
}
