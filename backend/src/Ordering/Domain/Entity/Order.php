<?php

declare(strict_types=1);

namespace App\Ordering\Domain\Entity;

use App\Ordering\Domain\ValueObject\OrderSource;
use App\Ordering\Domain\ValueObject\OrderStatus;
use Symfony\Component\Uid\Uuid;

class Order
{
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    /** @var OrderLine[] */
    private array $lines = [];

    private string $statusValue;
    private string $sourceValue;

    public function __construct(
        private Uuid $id,
        private Uuid $restaurantId,
        OrderStatus $status,
        OrderSource $source,
        private ?string $tableNumber,
        private ?string $customerPhone,
        private ?string $notes,
    ) {
        $this->statusValue = $status->value;
        $this->sourceValue = $source->value;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public static function place(
        Uuid $restaurantId,
        OrderSource $source,
        ?string $tableNumber,
        ?string $customerPhone,
        ?string $notes = null,
    ): self {
        return new self(
            Uuid::v7(),
            $restaurantId,
            OrderStatus::Pending,
            $source,
            $tableNumber,
            $customerPhone,
            $notes,
        );
    }

    public function addLine(Uuid $menuItemId, string $menuItemName, int $quantity, float $unitPrice, string $currency): void
    {
        $this->lines[] = new OrderLine(
            Uuid::v7(),
            $this->id,
            $menuItemId,
            $menuItemName,
            $quantity,
            $unitPrice,
            $currency,
        );
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function updateStatus(OrderStatus $newStatus): void
    {
        $current = OrderStatus::from($this->statusValue);
        $this->statusValue = $current->transitionTo($newStatus)->value;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getTotal(): float
    {
        return round(array_sum(array_map(fn(OrderLine $l) => $l->getLineTotal(), $this->lines)), 2);
    }

    public function getCurrency(): string
    {
        return empty($this->lines) ? 'EUR' : $this->lines[0]->getCurrency();
    }

    public function getId(): Uuid { return $this->id; }
    public function getRestaurantId(): Uuid { return $this->restaurantId; }
    public function getStatus(): OrderStatus { return OrderStatus::from($this->statusValue); }
    public function getSource(): OrderSource { return OrderSource::from($this->sourceValue); }
    public function getTableNumber(): ?string { return $this->tableNumber; }
    public function getCustomerPhone(): ?string { return $this->customerPhone; }
    public function getNotes(): ?string { return $this->notes; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    /** @return OrderLine[] */
    public function getLines(): array { return $this->lines; }

    /** @param OrderLine[] $lines */
    public function reconstituteLinesFromPersistence(array $lines): void
    {
        $this->lines = $lines;
    }
}
