<?php

declare(strict_types=1);

namespace App\Ordering\Domain\Entity;

use App\Ordering\Domain\ValueObject\OrderStatus;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'order_lines')]
class OrderLine
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(type: 'uuid')]
    private Uuid $orderId;

    #[ORM\Column(type: 'uuid')]
    private Uuid $menuItemId;

    #[ORM\Column(type: 'string', length: 255)]
    private string $menuItemName;

    #[ORM\Column(type: 'integer')]
    private int $quantity;

    #[ORM\Column(type: 'float')]
    private float $unitPrice;

    #[ORM\Column(type: 'string', length: 3)]
    private string $currency;

    public function __construct(
        Uuid $id,
        Uuid $orderId,
        Uuid $menuItemId,
        string $menuItemName,
        int $quantity,
        float $unitPrice,
        string $currency,
    ) {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Quantity must be at least 1.');
        }
        $this->id = $id;
        $this->orderId = $orderId;
        $this->menuItemId = $menuItemId;
        $this->menuItemName = $menuItemName;
        $this->quantity = $quantity;
        $this->unitPrice = $unitPrice;
        $this->currency = $currency;
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
