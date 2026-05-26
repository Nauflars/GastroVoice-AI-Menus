<?php

declare(strict_types=1);

namespace App\RestaurantManagement\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'opening_hours')]
class OpeningHour
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(type: 'integer')]
    private int $dayOfWeek; // 0=Monday, 6=Sunday

    #[ORM\Column(type: 'string', length: 5, nullable: true)]
    private ?string $openTime; // HH:MM format, null if closed

    #[ORM\Column(type: 'string', length: 5, nullable: true)]
    private ?string $closeTime;

    #[ORM\Column(type: 'boolean')]
    private bool $isClosed;

    #[ORM\Column(type: 'uuid')]
    private Uuid $restaurantId;

    public function __construct(
        Uuid $id,
        Uuid $restaurantId,
        int $dayOfWeek,
        bool $isClosed,
        ?string $openTime = null,
        ?string $closeTime = null,
    ) {
        if ($dayOfWeek < 0 || $dayOfWeek > 6) {
            throw new \InvalidArgumentException('Day of week must be between 0 (Monday) and 6 (Sunday).');
        }
        if (!$isClosed && (null === $openTime || null === $closeTime)) {
            throw new \InvalidArgumentException('Open time and close time are required when the restaurant is open.');
        }
        $this->id = $id;
        $this->restaurantId = $restaurantId;
        $this->dayOfWeek = $dayOfWeek;
        $this->isClosed = $isClosed;
        $this->openTime = $openTime;
        $this->closeTime = $closeTime;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getRestaurantId(): Uuid
    {
        return $this->restaurantId;
    }

    public function getDayOfWeek(): int
    {
        return $this->dayOfWeek;
    }

    public function getOpenTime(): ?string
    {
        return $this->openTime;
    }

    public function getCloseTime(): ?string
    {
        return $this->closeTime;
    }

    public function isClosed(): bool
    {
        return $this->isClosed;
    }
}
