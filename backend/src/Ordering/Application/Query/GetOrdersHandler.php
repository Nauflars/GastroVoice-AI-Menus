<?php

declare(strict_types=1);

namespace App\Ordering\Application\Query;

use App\Ordering\Domain\Entity\Order;
use App\Ordering\Domain\Repository\OrderRepositoryInterface;
use App\Ordering\Domain\ValueObject\OrderStatus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class GetOrdersHandler
{
    public function __construct(private OrderRepositoryInterface $orders) {}

    public function __invoke(GetOrdersQuery $query): array
    {
        $status = $query->status !== null ? OrderStatus::from($query->status) : null;
        $orders = $this->orders->findByRestaurant(Uuid::fromString($query->restaurantId), $status);

        return array_map(fn(Order $o) => [
            'id' => (string) $o->getId(),
            'status' => $o->getStatus()->value,
            'source' => $o->getSource()->value,
            'tableNumber' => $o->getTableNumber(),
            'customerPhone' => $o->getCustomerPhone(),
            'notes' => $o->getNotes(),
            'total' => $o->getTotal(),
            'currency' => $o->getCurrency(),
            'createdAt' => $o->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $o->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            'lines' => array_map(fn($l) => [
                'id' => (string) $l->getId(),
                'menuItemId' => (string) $l->getMenuItemId(),
                'menuItemName' => $l->getMenuItemName(),
                'quantity' => $l->getQuantity(),
                'unitPrice' => $l->getUnitPrice(),
                'currency' => $l->getCurrency(),
            ], $o->getLines()),
        ], $orders);
    }
}
