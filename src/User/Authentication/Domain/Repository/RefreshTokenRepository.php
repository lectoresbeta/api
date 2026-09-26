<?php

declare(strict_types=1);

namespace LectoresBeta\User\Authentication\Domain\Repository;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Authentication\Domain\Entity\RefreshToken;

interface RefreshTokenRepository
{
    public function save(RefreshToken $token): void;

    /**
     * Looked up by hash: the plain token exists only in the client
     * (`FEAT-USR-004` `RN-9`).
     *
     * Returns revoked and expired tokens too — the caller has to tell a
     * revoked one apart to apply `RN-11`.
     */
    public function ofTokenHash(string $tokenHash): ?RefreshToken;

    /**
     * Revokes every session of a user. Used when a revoked token is presented
     * again (`RN-11`) and when the password or the email changes
     * (`decision:0007` `RN-3`).
     *
     * @return int how many were revoked
     */
    public function revokeAllOf(UserId $userId, \DateTimeImmutable $now): int;
}
