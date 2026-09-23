<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Service;

use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\Email;
use LectoresBeta\User\Account\Domain\ValueObject\Username;
use LectoresBeta\User\Profile\Domain\Repository\UsernameAliasRepository;

/**
 * Turns an email into a free username (`FEAT-USR-001` `RN-2`).
 *
 * It lives in Application and not in Domain because it needs two repositories
 * to answer one question. A name is taken if a user holds it **or** if a live
 * alias still reserves it (`decision:0005`), and no database constraint spans
 * both tables — so this is the only place where the answer is complete, and
 * worth knowing before assuming the unique index is enough.
 */
final readonly class UsernameAllocator
{
    /**
     * A ceiling rather than an endless loop. Reaching it means something is
     * wrong — thousands of accounts on one email local part — and failing
     * loudly beats spinning.
     */
    private const MAX_ATTEMPTS = 1000;

    public function __construct(
        private UserRepository $users,
        private UsernameAliasRepository $aliases,
        private Clock $clock,
    ) {
    }

    public function allocateFrom(Email $email): Username
    {
        for ($suffix = 0; $suffix < self::MAX_ATTEMPTS; ++$suffix) {
            $candidate = Username::candidateFrom($email->localPart(), $suffix);

            if (!$this->isTaken($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Could not allocate a username after 1000 attempts.');
    }

    private function isTaken(Username $username): bool
    {
        return $this->users->usernameIsTaken($username)
            || $this->aliases->isHeldAt($username, $this->clock->now());
    }
}
