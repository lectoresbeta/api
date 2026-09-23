<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Credits\Account\Domain\Entity\CreditAccount;
use LectoresBeta\Credits\Account\Domain\Repository\CreditAccountRepository;
use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<CreditAccount>
 */
final class DoctrineCreditAccountRepository extends DoctrineRepository implements CreditAccountRepository
{
    public function save(CreditAccount $account): void
    {
        $this->register($account);
    }

    public function ofUser(UserId $userId): ?CreditAccount
    {
        return $this->repository()->find($userId->value());
    }

    public function totalBalance(): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COALESCE(SUM(a.balance), 0)')
            ->from(CreditAccount::class, 'a')
            ->getQuery()
            ->getSingleScalarResult();
    }

    protected function entityClass(): string
    {
        return CreditAccount::class;
    }
}
