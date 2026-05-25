<?php

declare(strict_types=1);

namespace App\Ordering\Application\Command;

use App\Ordering\Domain\Repository\OrderRepositoryInterface;
use App\Ordering\Domain\ValueObject\OrderStatus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class UpdateOrderStatusHandler
{
    public function __construct(private OrderRepositoryInterface $orders) {}

    public function __invoke(UpdateOrderStatusCommand $command): void
    {
        $order = $this->orders->findById(Uuid::fromString($command->orderId));
        if ($order === null) {
            throw new \DomainException('Order not found.');
        }

        $newStatus = OrderStatus::from($command->status);
        $order->updateStatus($newStatus);
        $this->orders->save($order);
    }
}
