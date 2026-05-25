<?php

declare(strict_types=1);

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Entity\AdminUser;
use Symfony\Component\Uid\Uuid;

interface AdminUserRepositoryInterface
{
    public function findByEmail(string $email): ?AdminUser;

    public function findById(Uuid $id): ?AdminUser;

    public function save(AdminUser $user): void;
}
