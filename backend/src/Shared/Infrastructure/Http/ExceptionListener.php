<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Reservations\Domain\Exception\SlotFullException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

#[AsEventListener]
final class ExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // Unwrap Messenger handler exceptions
        if ($exception instanceof HandlerFailedException) {
            $exception = $exception->getPrevious() ?? $exception;
        }

        // Let Symfony handle its own HTTP exceptions (404, 405, etc.)
        if ($exception instanceof HttpExceptionInterface) {
            return;
        }

        $statusCode = $this->resolveStatusCode($exception);

        $event->setResponse(new JsonResponse(
            ['error' => $exception->getMessage()],
            $statusCode,
        ));
    }

    private function resolveStatusCode(\Throwable $e): int
    {
        return match (true) {
            $e instanceof SlotFullException => Response::HTTP_CONFLICT,
            $e instanceof \DomainException  => Response::HTTP_UNPROCESSABLE_ENTITY,
            $e instanceof \InvalidArgumentException => Response::HTTP_BAD_REQUEST,
            default => Response::HTTP_INTERNAL_SERVER_ERROR,
        };
    }
}
