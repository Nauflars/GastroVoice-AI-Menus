<?php

declare(strict_types=1);

namespace App\Ordering\Domain\Entity;

use App\Ordering\Domain\ValueObject\OrderStatus;
use Symfony\Component\Uid\Uuid;

class OrderLine
{
    public function __construct(
        private Uuid $id,
        private Uuid $orderId,
        private Uuid $menuItemId,
        private string $menuItemName,
        private int $quantity,
        private float $unitPrice,
        private string $currency,
    ) {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Quantity must be at least 1.');
        }
    }

    public function getId(): Uuid { return $this->id; }
    public function getOrderId(): Uuid { return $this->orderId; }
    public function getMenuItemId(): Uuid { return $this->menuItemId; }
    public function getMenuItemName(): string { return $this->menuItemName; }
    public function getQuantity(): int { return $this->quantity; }
    public function getUnitPrice(): float { return $this->unitPrice; }
    public function getCurrency(): string { return $this->currency; }
    public function getLineTotal(): float { return round($this->unitPrice * $this->quantity, 2); }
}
