<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\User\Account\Domain\Entity\User;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\Email;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Account\Domain\ValueObject\Username;

/**
 * @extends DoctrineRepository<User>
 */
final class DoctrineUserRepository extends DoctrineRepository implements UserRepository
{
    public function save(User $user): void
    {
        $this->register($user);
    }

    public function ofId(UserId $id): ?User
    {
        return $this->repository()->find($id->value());
    }

    public function ofEmail(Email $email): ?User
    {
        return $this->repository()->findOneBy(['email' => $email->value()]);
    }

    public function ofUsername(Username $username): ?User
    {
        return $this->repository()->findOneBy(['username' => $username->value()]);
    }

    public function emailIsTaken(Email $email): bool
    {
        return $this->repository()->count(['email' => $email->value()]) > 0;
    }

    public function usernameIsTaken(Username $username): bool
    {
        return $this->repository()->count(['username' => $username->value()]) > 0;
    }

    protected function entityClass(): string
    {
        return User::class;
    }
}
