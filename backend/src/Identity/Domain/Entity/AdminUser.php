<?php

declare(strict_types=1);

namespace App\Identity\Domain\Entity;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

class AdminUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    private Uuid $id;
    private string $email;
    private string $passwordHash;
    private ?Uuid $restaurantId;
    /** @var list<string> */
    private array $roles;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        Uuid $id,
        string $email,
        string $passwordHash,
        ?Uuid $restaurantId = null,
        array $roles = ['ROLE_ADMIN'],
    ) {
        $this->id = $id;
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->restaurantId = $restaurantId;
        $this->roles = $roles;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    public function getRestaurantId(): ?Uuid
    {
        return $this->restaurantId;
    }

    public function assignRestaurant(Uuid $restaurantId): void
    {
        $this->restaurantId = $restaurantId;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return array_unique([...$this->roles, 'ROLE_ADMIN']);
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function eraseCredentials(): void
    {
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
