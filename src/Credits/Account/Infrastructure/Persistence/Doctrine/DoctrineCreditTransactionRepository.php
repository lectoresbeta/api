<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Credits\Account\Domain\Entity\CreditTransaction;
use LectoresBeta\Credits\Account\Domain\Enum\CreditTransactionReason;
use LectoresBeta\Credits\Account\Domain\Repository\CreditTransactionRepository;
use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<CreditTransaction>
 */
final class DoctrineCreditTransactionRepository extends DoctrineRepository implements CreditTransactionRepository
{
    public function add(CreditTransaction $transaction): void
    {
        $this->register($transaction);
    }

    public function historyOf(UserId $userId, int $limit = 50, int $offset = 0): array
    {
        /** @var list<CreditTransaction> $transactions */
        $transactions = $this->repository()->findBy(
            ['userId' => $userId->value()],
            ['occurredAt' => 'DESC', 'id' => 'DESC'],
            $limit,
            $offset,
        );

        return $transactions;
    }

    public function balanceOf(UserId $userId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COALESCE(SUM(t.amount), 0)')
            ->from(CreditTransaction::class, 't')
            ->where('t.userId = :userId')
            ->setParameter('userId', $userId->value())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function hasMovementWithReason(UserId $userId, CreditTransactionReason $reason): bool
    {
        // `SELECT 1 ... LIMIT 1`: the question is whether one exists, and
        // counting them all to answer it would read the whole history.
        return null !== $this->entityManager->createQueryBuilder()
            ->select('t.id')
            ->from(CreditTransaction::class, 't')
            ->where('t.userId = :userId')
            ->andWhere('t.reason = :reason')
            ->setParameter('userId', $userId->value())
            ->setParameter('reason', $reason)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function totalIssued(): int
    {
        $taps = array_values(array_filter(
            CreditTransactionReason::cases(),
            static fn (CreditTransactionReason $reason): bool => $reason->isTap(),
        ));

        return (int) $this->entityManager->createQueryBuilder()
            ->select('COALESCE(SUM(t.amount), 0)')
            ->from(CreditTransaction::class, 't')
            ->where('t.reason IN (:taps)')
            ->setParameter('taps', $taps)
            ->getQuery()
            ->getSingleScalarResult();
    }

    protected function entityClass(): string
    {
        return CreditTransaction::class;
    }
}
