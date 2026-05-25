<?php

declare(strict_types=1);

namespace App\VoiceAssistant\Infrastructure\Persistence;

use App\VoiceAssistant\Domain\Entity\CallSession;
use App\VoiceAssistant\Domain\Repository\CallSessionRepositoryInterface;
use App\VoiceAssistant\Domain\ValueObject\ConversationTurn;
use Predis\ClientInterface as RedisClient;
use Symfony\Component\Uid\Uuid;

final class RedisCallSessionRepository implements CallSessionRepositoryInterface
{
    private const TTL_SECONDS = 7200; // 2 hours

    public function __construct(private RedisClient $redis) {}

    public function findById(Uuid $id): ?CallSession
    {
        $key  = $this->key($id);
        $json = $this->redis->get($key);
        if ($json === null) {
            return null;
        }

        $data = json_decode((string)$json, true);
        if ($data === null) {
            return null;
        }

        return $this->deserialize($data);
    }

    public function save(CallSession $session): void
    {
        $key  = $this->key($session->getId());
        $json = json_encode($this->serialize($session), JSON_THROW_ON_ERROR);
        $this->redis->setex($key, self::TTL_SECONDS, $json);
    }

    public function delete(Uuid $id): void
    {
        $this->redis->del([$this->key($id)]);
    }

    private function key(Uuid $id): string
    {
        return 'call_session:' . (string) $id;
    }

    private function serialize(CallSession $session): array
    {
        return [
            'id'          => (string) $session->getId(),
            'restaurantId'=> $session->getRestaurantId(),
            'callerId'    => $session->getCallerId(),
            'state'       => $session->getState(),
            'pendingData' => $session->getPendingData(),
            'history'     => array_map(fn(ConversationTurn $t) => $t->toArray(), $session->getHistory()),
            'startedAt'   => $session->getStartedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function deserialize(array $data): CallSession
    {
        $session = CallSession::start($data['restaurantId'], $data['callerId']);

        // Reconstruct UUID — use reflection to set the private id
        $ref = new \ReflectionClass($session);
        $idProp = $ref->getProperty('id');
        $idProp->setAccessible(true);
        $idProp->setValue($session, Uuid::fromString($data['id']));

        foreach ($data['history'] ?? [] as $turn) {
            if ($turn['role'] === 'user') {
                $session->addTurn(ConversationTurn::user($turn['content']));
            } else {
                $session->addTurn(ConversationTurn::assistant($turn['content']));
            }
        }

        if (!empty($data['pendingData'])) {
            $session->updatePendingData($data['pendingData']);
        }

        if ($data['state'] === 'completed') {
            $session->complete();
        } elseif ($data['state'] === 'abandoned') {
            $session->abandon();
        }

        return $session;
    }
}
