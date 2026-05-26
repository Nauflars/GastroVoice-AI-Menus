<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Domain\Bus\CommandBusInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final class MessengerCommandBus implements CommandBusInterface
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function dispatch(object $command): mixed
    {
        try {
            $envelope = $this->commandBus->dispatch($command);
            /** @var HandledStamp|null $stamp */
            $stamp = $envelope->last(HandledStamp::class);
            return $stamp?->getResult();
        } catch (HandlerFailedException $e) {
            throw $e->getPrevious() ?? $e;
        }
    }
}
