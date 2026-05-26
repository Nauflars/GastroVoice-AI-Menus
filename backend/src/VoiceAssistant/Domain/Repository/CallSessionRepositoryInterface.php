<?php

declare(strict_types=1);

namespace App\VoiceAssistant\Domain\Repository;

use App\VoiceAssistant\Domain\Entity\CallSession;
use Symfony\Component\Uid\Uuid;

interface CallSessionRepositoryInterface
{
    public function findById(Uuid $id): ?CallSession;
    public function save(CallSession $session): void;
    public function delete(Uuid $id): void;
}
