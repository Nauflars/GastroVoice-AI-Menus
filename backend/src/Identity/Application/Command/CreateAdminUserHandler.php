<?php

declare(strict_types=1);

namespace App\Identity\Application\Command;

use App\Identity\Domain\Entity\AdminUser;
use App\Identity\Domain\Repository\AdminUserRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class CreateAdminUserHandler
{
    public function __construct(
        private readonly AdminUserRepositoryInterface $repository,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    public function __invoke(CreateAdminUserCommand $command): AdminUser
    {
        $existing = $this->repository->findByEmail($command->email);
        if (null !== $existing) {
            throw new \DomainException(\sprintf('Admin user with email "%s" already exists.', $command->email));
        }

        $user = new AdminUser(
            id: Uuid::v7(),
            email: $command->email,
            passwordHash: '',
        );

        $hash = $this->hasher->hashPassword($user, $command->plainPassword);
        $user = new AdminUser(
            id: $user->getId(),
            email: $user->getEmail(),
            passwordHash: $hash,
        );

        $this->repository->save($user);

        return $user;
    }
}
