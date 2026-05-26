<?php

declare(strict_types=1);

namespace App\RestaurantManagement\Domain\ValueObject;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final class SlotDuration
{
    #[ORM\Column(type: 'integer', name: 'minutes')]
    private int $minutes;

    public function __construct(int $minutes)
    {
        if ($minutes < 15) {
            throw new \InvalidArgumentException('Slot duration must be at least 15 minutes.');
        }
        if ($minutes % 5 !== 0) {
            throw new \InvalidArgumentException('Slot duration must be a multiple of 5 minutes.');
        }
        $this->minutes = $minutes;
    }

    public function minutes(): int
    {
        return $this->minutes;
    }

    public function equals(self $other): bool
    {
        return $this->minutes === $other->minutes;
    }
}
