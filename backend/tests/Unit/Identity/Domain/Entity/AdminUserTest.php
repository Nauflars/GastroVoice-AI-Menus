<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain\Entity;

use App\Identity\Domain\Entity\AdminUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class AdminUserTest extends TestCase
{
    private Uuid $id;

    protected function setUp(): void
    {
        $this->id = Uuid::v7();
    }

    public function testCreateAdminUser(): void
    {
        $user = new AdminUser(
            id: $this->id,
            email: 'admin@gastrovoice.com',
            passwordHash: 'hashed_password',
        );

        self::assertSame((string) $this->id, (string) $user->getId());
        self::assertSame('admin@gastrovoice.com', $user->getEmail());
        self::assertSame('hashed_password', $user->getPasswordHash());
        self::assertNull($user->getRestaurantId());
    }

    public function testGetUserIdentifierReturnsEmail(): void
    {
        $user = new AdminUser($this->id, 'chef@restaurant.com', 'hash');

        self::assertSame('chef@restaurant.com', $user->getUserIdentifier());
    }

    public function testRolesAlwaysContainRoleAdmin(): void
    {
        $user = new AdminUser($this->id, 'admin@gastrovoice.com', 'hash', null, ['ROLE_USER']);

        self::assertContains('ROLE_ADMIN', $user->getRoles());
    }

    public function testAssignRestaurant(): void
    {
        $user = new AdminUser($this->id, 'admin@gastrovoice.com', 'hash');
        $restaurantId = Uuid::v7();

        $user->assignRestaurant($restaurantId);

        self::assertSame((string) $restaurantId, (string) $user->getRestaurantId());
    }

    public function testCreatedAtIsSetOnConstruction(): void
    {
        $before = new \DateTimeImmutable();
        $user = new AdminUser($this->id, 'admin@gastrovoice.com', 'hash');
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before->getTimestamp(), $user->getCreatedAt()->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $user->getCreatedAt()->getTimestamp());
    }
}
