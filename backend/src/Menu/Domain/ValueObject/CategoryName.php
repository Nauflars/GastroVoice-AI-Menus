<?php

declare(strict_types=1);

namespace App\Menu\Domain\ValueObject;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final class CategoryName
{
    #[ORM\Column(type: 'string', length: 150, name: 'name')]
    private string $value = '';

    public function __construct(string $value = '') {
        $this->value = $value;
    }

    public static function of(string $value): self
    {
        $value = trim($value);
        if ($value === '') {
            throw new \InvalidArgumentException('Category name cannot be empty.');
        }
        if (strlen($value) > 150) {
            throw new \InvalidArgumentException('Category name must not exceed 150 characters.');
        }
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return strtolower($this->value) === strtolower($other->value);
    }
}
