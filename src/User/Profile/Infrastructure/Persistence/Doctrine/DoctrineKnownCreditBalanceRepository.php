<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Profile\Domain\Entity\KnownCreditBalance;
use LectoresBeta\User\Profile\Domain\Repository\KnownCreditBalanceRepository;

/**
 * @extends DoctrineRepository<KnownCreditBalance>
 */
final class DoctrineKnownCreditBalanceRepository extends DoctrineRepository implements KnownCreditBalanceRepository
{
    public function save(KnownCreditBalance $balance): void
    {
        $this->register($balance);
    }

    public function ofUser(UserId $userId): ?KnownCreditBalance
    {
        return $this->repository()->find($userId->value());
    }

    protected function entityClass(): string
    {
        return KnownCreditBalance::class;
    }
}
