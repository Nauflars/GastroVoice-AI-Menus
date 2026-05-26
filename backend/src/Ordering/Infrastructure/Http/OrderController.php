<?php

declare(strict_types=1);

namespace App\Ordering\Infrastructure\Http;

use App\Ordering\Application\Command\PlaceOrderCommand;
use App\Ordering\Application\Command\PlaceOrderLineDTO;
use App\Ordering\Application\Command\UpdateOrderStatusCommand;
use App\Ordering\Application\Query\GetOrdersQuery;
use App\Shared\Domain\Bus\CommandBusInterface;
use App\Shared\Domain\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/orders')]
final class OrderController extends AbstractController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId();
        $status = $request->query->get('status');

        $orders = $this->queryBus->ask(new GetOrdersQuery($restaurantId, $status));
        return $this->json($orders);
    }

    #[Route('', methods: ['POST'])]
    public function place(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $restaurantId = $this->getRestaurantIdOrFromBody($data);

        $lines = array_map(fn(array $l) => new PlaceOrderLineDTO(
            $l['menuItemId'],
            $l['menuItemName'],
            (int) $l['quantity'],
            (float) $l['unitPrice'],
            $l['currency'] ?? 'EUR',
        ), $data['lines'] ?? []);

        try {
            $order = $this->commandBus->dispatch(new PlaceOrderCommand(
                $restaurantId,
                $data['source'] ?? 'manual',
                $data['tableNumber'] ?? null,
                $data['customerPhone'] ?? null,
                $lines,
                $data['notes'] ?? null,
            ));
            return $this->json(['id' => (string) $order->getId()], Response::HTTP_CREATED);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/{id}', methods: ['PATCH'])]
    public function updateStatus(string $id, Request $request): JsonResponse
    {
        $data = $request->toArray();

        try {
            $this->commandBus->dispatch(new UpdateOrderStatusCommand($id, $data['status'] ?? ''));
            return $this->json(null, Response::HTTP_NO_CONTENT);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    private function getRestaurantId(): string
    {
        /** @var \App\Identity\Domain\Entity\AdminUser $user */
        $user = $this->getUser();
        $restaurantId = $user->getRestaurantId();
        if ($restaurantId === null) {
            throw new \DomainException('No restaurant assigned to this user.');
        }
        return (string) $restaurantId;
    }

    /**
     * Voice/phone orders are placed from the Asterisk AGI (no JWT user context),
     * so restaurantId must come from the request body in that case.
     */
    private function getRestaurantIdOrFromBody(array $data): string
    {
        $user = $this->getUser();
        if ($user instanceof \App\Identity\Domain\Entity\AdminUser && $user->getRestaurantId() !== null) {
            return (string) $user->getRestaurantId();
        }
        return $data['restaurantId'] ?? throw new \DomainException('restaurantId required.');
    }
}
