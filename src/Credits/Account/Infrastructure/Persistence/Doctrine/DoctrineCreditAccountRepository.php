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

    public function balanceSpread(): array
    {
        /** @var array{total: int|string, at_zero_or_below: int|string, in_debt: int|string, deepest: int|string|null} $row */
        $row = $this->entityManager->getConnection()->fetchAssociative(<<<'SQL'
            SELECT
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE balance <= 0) AS at_zero_or_below,
                COUNT(*) FILTER (WHERE balance < 0) AS in_debt,
                MIN(balance) AS deepest
            FROM credits_ctx.credit_account
            SQL) ?: ['total' => 0, 'at_zero_or_below' => 0, 'in_debt' => 0, 'deepest' => 0];

        return [
            'total' => (int) $row['total'],
            'atZeroOrBelow' => (int) $row['at_zero_or_below'],
            'inDebt' => (int) $row['in_debt'],
            'deepestDebt' => min(0, (int) ($row['deepest'] ?? 0)),
        ];
    }

    protected function entityClass(): string
    {
        return CreditAccount::class;
    }
}
