<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence;

use App\Identity\Domain\Entity\AdminUser;
use App\Identity\Domain\Repository\AdminUserRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineAdminUserRepository implements AdminUserRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function findByEmail(string $email): ?AdminUser
    {
        return $this->em->getRepository(AdminUser::class)->findOneBy(['email' => $email]);
    }

    public function findById(Uuid $id): ?AdminUser
    {
        return $this->em->find(AdminUser::class, $id);
    }

    public function save(AdminUser $user): void
    {
        $this->em->persist($user);
        $this->em->flush();
    }
}
