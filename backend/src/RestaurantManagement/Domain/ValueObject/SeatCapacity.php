<?php

declare(strict_types=1);

namespace App\RestaurantManagement\Domain\ValueObject;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final class SeatCapacity
{
    #[ORM\Column(type: 'integer', name: 'value')]
    private int $value;

    public function __construct(int $value)
    {
        if ($value < 1) {
            throw new \InvalidArgumentException('Seat capacity must be at least 1.');
        }
        $this->value = $value;
    }

    public function value(): int
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
