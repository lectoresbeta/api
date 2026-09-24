<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Service;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\User\Account\Application\Contract\RegisteredUsers;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;

/**
 * `User`'s side of the «does this person exist» contract.
 *
 * A malformed identifier answers `false` rather than throwing: it arrives
 * from a request body, and whoever asks has to handle «no» anyway. One answer
 * instead of two paths is one less place to get it wrong.
 */
final readonly class CheckRegisteredUsers implements RegisteredUsers
{
    public function __construct(private UserRepository $users)
    {
    }

    public function exists(string $userId): bool
    {
        try {
            return null !== $this->users->ofId(UserId::fromString($userId));
        } catch (InvalidValue) {
            return false;
        }
    }
}
