<?php

declare(strict_types=1);

namespace App\Menu\Domain\ValueObject;

final class CategoryName
{
    private function __construct(private readonly string $value) {}

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
