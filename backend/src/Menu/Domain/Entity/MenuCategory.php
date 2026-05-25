<?php

declare(strict_types=1);

namespace App\Menu\Domain\Entity;

use App\Menu\Domain\ValueObject\CategoryName;
use Symfony\Component\Uid\Uuid;

class MenuCategory
{
    private \DateTimeImmutable $createdAt;

    public function __construct(
        private Uuid $id,
        private Uuid $restaurantId,
        private CategoryName $name,
        private int $displayOrder,
        private bool $isActive = true,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function create(Uuid $restaurantId, CategoryName $name, int $displayOrder = 0): self
    {
        return new self(Uuid::v7(), $restaurantId, $name, $displayOrder);
    }

    public function getId(): Uuid { return $this->id; }
    public function getRestaurantId(): Uuid { return $this->restaurantId; }
    public function getName(): CategoryName { return $this->name; }
    public function getDisplayOrder(): int { return $this->displayOrder; }
    public function isActive(): bool { return $this->isActive; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function rename(CategoryName $name): void
    {
        $this->name = $name;
    }

    public function reorder(int $displayOrder): void
    {
        $this->displayOrder = $displayOrder;
    }

    public function deactivate(): void
    {
        $this->isActive = false;
    }

    public function activate(): void
    {
        $this->isActive = true;
    }
}
