<?php

declare(strict_types=1);

namespace App\Menu\Domain\Entity;

use App\Menu\Domain\ValueObject\Price;
use Symfony\Component\Uid\Uuid;

class MenuItem
{
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        private Uuid $id,
        private Uuid $categoryId,
        private string $name,
        private ?string $description,
        private Price $price,
        private bool $isAvailable = true,
    ) {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public static function create(Uuid $categoryId, string $name, ?string $description, Price $price): self
    {
        if (trim($name) === '') {
            throw new \InvalidArgumentException('Menu item name cannot be empty.');
        }
        return new self(Uuid::v7(), $categoryId, trim($name), $description, $price);
    }

    public function getId(): Uuid { return $this->id; }
    public function getCategoryId(): Uuid { return $this->categoryId; }
    public function getName(): string { return $this->name; }
    public function getDescription(): ?string { return $this->description; }
    public function getPrice(): Price { return $this->price; }
    public function isAvailable(): bool { return $this->isAvailable; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function updateDetails(string $name, ?string $description, Price $price): void
    {
        if (trim($name) === '') {
            throw new \InvalidArgumentException('Menu item name cannot be empty.');
        }
        $this->name = trim($name);
        $this->description = $description;
        $this->price = $price;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function toggleAvailability(): void
    {
        $this->isAvailable = !$this->isAvailable;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
