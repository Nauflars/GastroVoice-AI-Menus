<?php

declare(strict_types=1);

namespace App\Ordering\Application\Command;

use App\Ordering\Domain\Entity\Order;
use App\Ordering\Domain\Repository\OrderRepositoryInterface;
use App\Ordering\Domain\ValueObject\OrderSource;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class PlaceOrderHandler
{
    public function __construct(private OrderRepositoryInterface $orders) {}

    public function __invoke(PlaceOrderCommand $command): Order
    {
        if (empty($command->lines)) {
            throw new \DomainException('An order must have at least one line.');
        }

        $order = Order::place(
            Uuid::fromString($command->restaurantId),
            OrderSource::from($command->source),
            $command->tableNumber,
            $command->customerPhone,
            $command->notes,
        );

        foreach ($command->lines as $line) {
            $order->addLine(
                Uuid::fromString($line->menuItemId),
                $line->menuItemName,
                $line->quantity,
                $line->unitPrice,
                $line->currency,
            );
        }

        $this->orders->save($order);
        return $order;
    }
}
