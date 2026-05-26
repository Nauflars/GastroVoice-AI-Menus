<?php

declare(strict_types=1);

namespace App\Menu\Domain\ValueObject;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final class Price
{
    #[ORM\Column(type: 'float', name: 'amount')]
    private float $amount = 0.0;

    #[ORM\Column(type: 'string', length: 3, name: 'currency')]
    private string $currency = 'EUR';

    public function __construct(float $amount = 0.0, string $currency = 'EUR')
    {
        $this->amount = $amount;
        $this->currency = $currency;
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
