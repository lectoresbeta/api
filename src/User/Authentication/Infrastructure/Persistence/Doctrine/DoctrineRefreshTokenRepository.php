<?php

declare(strict_types=1);

namespace LectoresBeta\User\Authentication\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Authentication\Domain\Entity\RefreshToken;
use LectoresBeta\User\Authentication\Domain\Repository\RefreshTokenRepository;

/**
 * @extends DoctrineRepository<RefreshToken>
 */
final class DoctrineRefreshTokenRepository extends DoctrineRepository implements RefreshTokenRepository
{
    public function save(RefreshToken $token): void
    {
        $this->register($token);
    }

    public function ofTokenHash(string $tokenHash): ?RefreshToken
    {
        return $this->repository()->findOneBy(['tokenHash' => $tokenHash]);
    }

    public function revokeAllOf(UserId $userId, \DateTimeImmutable $now): int
    {
        // Loaded and revoked one by one, and **not** with a bulk `UPDATE`.
        //
        // A bulk update bypasses the unit of work, so a `RefreshToken`
        // already in memory goes on looking live — and the very next request
        // in a long-running worker would renew a session this call was meant
        // to kill. It cost a functional test to find out.
        //
        // The price is reading a handful of rows: sessions per person are
        // counted in tens, and this runs only when a stolen token is
        // suspected.
        $live = $this->repository()->findBy(['userId' => $userId->value(), 'revokedAt' => null]);

        foreach ($live as $token) {
            $token->revoke($now);
            $this->register($token);
        }

        return \count($live);
    }

    protected function entityClass(): string
    {
        return RefreshToken::class;
    }
}
