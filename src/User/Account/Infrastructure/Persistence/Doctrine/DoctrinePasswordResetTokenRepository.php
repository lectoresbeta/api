<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\User\Account\Domain\Entity\PasswordResetToken;
use LectoresBeta\User\Account\Domain\Repository\PasswordResetTokenRepository;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;

/**
 * @extends DoctrineRepository<PasswordResetToken>
 */
final class DoctrinePasswordResetTokenRepository extends DoctrineRepository implements PasswordResetTokenRepository
{
    public function save(PasswordResetToken $token): void
    {
        $this->register($token);
    }

    public function ofTokenHash(string $tokenHash): ?PasswordResetToken
    {
        return $this->repository()->findOneBy(['tokenHash' => $tokenHash]);
    }

    public function liveTokensOf(UserId $userId): array
    {
        return array_values($this->repository()->findBy([
            'userId' => $userId->value(),
            'usedAt' => null,
            'invalidatedAt' => null,
        ]));
    }

    protected function entityClass(): string
    {
        return PasswordResetToken::class;
    }
}
