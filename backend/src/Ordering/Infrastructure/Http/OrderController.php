<?php

declare(strict_types=1);

namespace App\Ordering\Infrastructure\Http;

use App\Ordering\Application\Command\PlaceOrderCommand;
use App\Ordering\Application\Command\PlaceOrderLineDTO;
use App\Ordering\Application\Command\UpdateOrderStatusCommand;
use App\Ordering\Application\Query\GetOrdersQuery;
use App\Ordering\Domain\Repository\OrderRepositoryInterface;
use App\Menu\Application\Query\GetActiveMenuQuery;
use App\Shared\Domain\Bus\CommandBusInterface;
use App\Shared\Domain\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/orders')]
final class OrderController extends AbstractController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
        private OrderRepositoryInterface $orderRepo,
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

        // Support both "lines" (full format) and "items" (voice format with just name+quantity)
        if (!empty($data['lines'])) {
            $lines = array_map(fn(array $l) => new PlaceOrderLineDTO(
                $l['menuItemId'],
                $l['menuItemName'],
                (int) $l['quantity'],
                (float) $l['unitPrice'],
                $l['currency'] ?? 'EUR',
            ), $data['lines']);
        } elseif (!empty($data['items'])) {
            $lines = $this->resolveItemsByName($restaurantId, $data['items']);
        } else {
            return $this->json(['error' => 'Either "lines" or "items" is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $order = $this->commandBus->dispatch(new PlaceOrderCommand(
                $restaurantId,
                $data['source'] ?? 'manual',
                $data['tableNumber'] ?? null,
                $data['customerPhone'] ?? null,
                $lines,
                $data['notes'] ?? null,
            ));
            return $this->json($this->serializeOrder($order), Response::HTTP_CREATED);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $order = $this->orderRepo->findById(Uuid::fromString($id));
        if ($order === null) {
            return $this->json(['code' => 'not_found', 'message' => 'Order not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeOrder($order));
    }

    #[Route('/{id}', methods: ['PATCH'])]
    public function updateStatus(string $id, Request $request): JsonResponse
    {
        $data = $request->toArray();

        try {
            $this->commandBus->dispatch(new UpdateOrderStatusCommand($id, $data['status'] ?? ''));
            $order = $this->orderRepo->findById(Uuid::fromString($id));
            return $this->json($this->serializeOrder($order));
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Resolves voice-provided items (name+quantity) against the active menu.
     * @return PlaceOrderLineDTO[]
     */
    private function resolveItemsByName(string $restaurantId, array $items): array
    {
        $menu = $this->queryBus->ask(new GetActiveMenuQuery($restaurantId));

        // Build a lookup index: lowercase name => {id, name, price, currency}
        $index = [];
        foreach ($menu as $category) {
            foreach ($category['items'] ?? [] as $menuItem) {
                $key = mb_strtolower(trim($menuItem['name']));
                $index[$key] = $menuItem;
            }
        }

        $lines = [];
        $notFound = [];
        foreach ($items as $item) {
            $name = $item['name'] ?? '';
            $qty  = (int) ($item['quantity'] ?? 1);
            $key  = mb_strtolower(trim($name));

            $found = $index[$key] ?? null;

            // Fuzzy match: partial substring
            if ($found === null) {
                foreach ($index as $menuKey => $menuItem) {
                    if (str_contains($menuKey, $key) || str_contains($key, $menuKey)) {
                        $found = $menuItem;
                        break;
                    }
                }
            }

            if ($found === null) {
                $notFound[] = $name;
                continue;
            }

            $lines[] = new PlaceOrderLineDTO(
                $found['id'],
                $found['name'],
                $qty,
                (float) $found['price'],
                $found['currency'] ?? 'EUR',
            );
        }

        if (!empty($notFound) && empty($lines)) {
            throw new \DomainException(sprintf('No se encontraron estos platos en el menú: %s', implode(', ', $notFound)));
        }

        return $lines;
    }

    private function serializeOrder(object $order): array
    {
        return [
            'id'            => (string) $order->getId(),
            'status'        => $order->getStatus()->value,
            'source'        => $order->getSource()->value,
            'tableNumber'   => $order->getTableNumber(),
            'customerPhone' => $order->getCustomerPhone(),
            'notes'         => $order->getNotes(),
            'totalAmount'   => $order->getTotal(),
            'lines'         => array_map(fn($l) => [
                'menuItemId'   => (string) $l->getMenuItemId(),
                'menuItemName' => $l->getMenuItemName(),
                'unitPrice'    => $l->getUnitPrice(),
                'quantity'     => $l->getQuantity(),
                'lineTotal'    => $l->getLineTotal(),
            ], $order->getLines()),
            'createdAt' => $order->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
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
