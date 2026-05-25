<?php

declare(strict_types=1);

namespace App\Menu\Domain\ValueObject;

final class Price
{
    private function __construct(private readonly float $amount, private readonly string $currency)
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Price amount cannot be negative.');
        }
        if (strlen(trim($currency)) !== 3) {
            throw new \InvalidArgumentException('Currency must be a 3-letter ISO code.');
        }
    }

    public static function of(float $amount, string $currency = 'EUR'): self
    {
        return new self(round($amount, 2), strtoupper($currency));
    }

    public function amount(): float
    {
        return $this->amount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function toString(): string
    {
        return number_format($this->amount, 2) . ' ' . $this->currency;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }
}
