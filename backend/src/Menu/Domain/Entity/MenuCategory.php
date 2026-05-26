<?php

declare(strict_types=1);

namespace App\Menu\Domain\Entity;

use App\Menu\Domain\ValueObject\CategoryName;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'menu_categories')]
class MenuCategory
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(type: 'uuid')]
    private Uuid $restaurantId;

    #[ORM\Embedded(class: CategoryName::class, columnPrefix: false)]
    private CategoryName $name;

    #[ORM\Column(type: 'integer')]
    private int $displayOrder;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        Uuid $id,
        Uuid $restaurantId,
        CategoryName $name,
        int $displayOrder,
        bool $isActive = true,
    ) {
        $this->id = $id;
        $this->restaurantId = $restaurantId;
        $this->name = $name;
        $this->displayOrder = $displayOrder;
        $this->isActive = $isActive;
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
