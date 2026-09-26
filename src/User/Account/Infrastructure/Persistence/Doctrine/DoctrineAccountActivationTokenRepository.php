<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\User\Account\Domain\Entity\AccountActivationToken;
use LectoresBeta\User\Account\Domain\Repository\AccountActivationTokenRepository;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;

/**
 * @extends DoctrineRepository<AccountActivationToken>
 */
final class DoctrineAccountActivationTokenRepository extends DoctrineRepository implements AccountActivationTokenRepository
{
    public function save(AccountActivationToken $token): void
    {
        $this->register($token);
    }

    public function ofTokenHash(string $tokenHash): ?AccountActivationToken
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
        return AccountActivationToken::class;
    }
}
