<?php

declare(strict_types=1);

namespace App\Ordering\Domain\Entity;

use App\Ordering\Domain\ValueObject\OrderSource;
use App\Ordering\Domain\ValueObject\OrderStatus;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'orders')]
class Order
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(type: 'uuid')]
    private Uuid $restaurantId;

    #[ORM\Column(type: 'string', length: 20, name: 'status')]
    private string $statusValue;

    #[ORM\Column(type: 'string', length: 20, name: 'source')]
    private string $sourceValue;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $tableNumber;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $customerPhone;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** @var OrderLine[] */
    private array $lines = [];

    public function __construct(
        Uuid $id,
        Uuid $restaurantId,
        OrderStatus $status,
        OrderSource $source,
        ?string $tableNumber,
        ?string $customerPhone,
        ?string $notes,
    ) {
        $this->id = $id;
        $this->restaurantId = $restaurantId;
        $this->statusValue = $status->value;
        $this->sourceValue = $source->value;
        $this->tableNumber = $tableNumber;
        $this->customerPhone = $customerPhone;
        $this->notes = $notes;
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
