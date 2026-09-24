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
     * People whose **name or username** match, for the directory
     * (`FEAT-RDG-006`).
     *
     * Never matches on email, and never returns an account that is not
     * `ACTIVE`: a deleted one is anonymised and has nothing left to match.
     * The two rules live in the query rather than in whoever calls it,
     * because a caller that has to remember them is a caller that one day
     * will not.
     *
     * @param int<1, 100> $limit
     *
     * @return list<User>
     */
    public function matching(string $query, int $limit): array;

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
