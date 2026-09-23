<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Repository;

use LectoresBeta\User\Account\Domain\Entity\User;
use LectoresBeta\User\Account\Domain\ValueObject\Email;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Account\Domain\ValueObject\Username;

/**
 * Access to the `User` aggregate.
 *
 * The contract lives in Domain and its Doctrine implementation in
 * Infrastructure. Application code depends on this interface and never on the
 * implementation (`AGENTS.md`).
 */
interface UserRepository
{
    public function save(User $user): void;

    public function ofId(UserId $id): ?User;

    public function ofEmail(Email $email): ?User;

    public function ofUsername(Username $username): ?User;

    /**
     * Whether the address is taken. The caller must not leak the answer: the
     * registration response may not reveal whether a given email has an
     * account (`FEAT-USR-001` `RN-14`).
     */
    public function emailIsTaken(Email $email): bool;

    /**
     * Only checks the `user` table. A username can also be held by a live
     * alias, and **no database constraint covers both** (`FEAT-USR-033`):
     * the cross-check is the application's job, which is worth knowing before
     * assuming a guarantee that does not exist.
     */
    public function usernameIsTaken(Username $username): bool;
}
