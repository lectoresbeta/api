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

    public function historyOf(
        UserId $userId,
        int $limit = 50,
        int $offset = 0,
        ?CreditTransactionReason $reason = null,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null,
    ): array {
        $query = $this->repository()->createQueryBuilder('t')
            ->where('t.userId = :user')
            ->setParameter('user', $userId->value())
            ->orderBy('t.occurredAt', 'DESC')
            ->addOrderBy('t.id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if (null !== $reason) {
            $query->andWhere('t.reason = :reason')->setParameter('reason', $reason);
        }

        if (null !== $from) {
            $query->andWhere('t.occurredAt >= :from')->setParameter('from', $from);
        }

        if (null !== $to) {
            $query->andWhere('t.occurredAt <= :to')->setParameter('to', $to);
        }

        return array_values($query->getQuery()->getResult());
    }

    public function sumAfter(UserId $userId, CreditTransaction $movement): int
    {
        // «Después» es por instante y, a igualdad de instante, por
        // identificador: dos movimientos de la misma transferencia comparten
        // segundo, y sin el desempate uno de los dos se contaría de más.
        return (int) $this->repository()->createQueryBuilder('t')
            ->select('COALESCE(SUM(t.amount), 0)')
            ->where('t.userId = :user')
            ->andWhere('t.occurredAt > :moment OR (t.occurredAt = :moment AND t.id > :id)')
            ->setParameter('user', $userId->value())
            ->setParameter('moment', $movement->occurredAt())
            ->setParameter('id', $movement->id()->value())
            ->getQuery()
            ->getSingleScalarResult();
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

    public function totalMoved(): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COALESCE(SUM(t.amount), 0)')
            ->from(CreditTransaction::class, 't')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function tallyOfReason(CreditTransactionReason $reason, ?\DateTimeImmutable $from, ?\DateTimeImmutable $to): array
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('COUNT(t.id) AS movements', 'COALESCE(SUM(t.amount), 0) AS net')
            ->from(CreditTransaction::class, 't')
            ->where('t.reason = :reason')
            ->setParameter('reason', $reason);

        if (null !== $from) {
            $query->andWhere('t.occurredAt >= :from')->setParameter('from', $from);
        }

        if (null !== $to) {
            $query->andWhere('t.occurredAt <= :to')->setParameter('to', $to);
        }

        /** @var array{movements: int|string, net: int|string} $row */
        $row = $query->getQuery()->getSingleResult();

        return ['count' => (int) $row['movements'], 'net' => (int) $row['net']];
    }

    public function ofCorrection(string $correctionId): array
    {
        // `metadata` es `jsonb` y esto es una consulta que DQL no sabe
        // expresar, así que es SQL de PostgreSQL — y por eso vive aquí, en
        // Infrastructure, y no en el contrato.
        /** @var list<string> $ids */
        $ids = $this->entityManager->getConnection()->fetchFirstColumn(
            "SELECT id FROM credits_ctx.credit_transaction
             WHERE metadata->>'correctionId' = :correction
             ORDER BY occurred_at ASC, id ASC",
            ['correction' => $correctionId],
        );

        if ([] === $ids) {
            return [];
        }

        /** @var list<CreditTransaction> $transactions */
        $transactions = $this->repository()->findBy(['id' => $ids], ['occurredAt' => 'ASC', 'id' => 'ASC']);

        return $transactions;
    }

    protected function entityClass(): string
    {
        return CreditTransaction::class;
    }
}
