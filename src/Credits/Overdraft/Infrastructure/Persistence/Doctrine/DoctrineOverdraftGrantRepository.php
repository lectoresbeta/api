<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Overdraft\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Credits\Overdraft\Domain\Entity\OverdraftGrant;
use LectoresBeta\Credits\Overdraft\Domain\Repository\OverdraftGrantRepository;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<OverdraftGrant>
 */
final class DoctrineOverdraftGrantRepository extends DoctrineRepository implements OverdraftGrantRepository
{
    public function save(OverdraftGrant $grant): void
    {
        $this->register($grant);
    }

    public function countInPeriod(string $quotaPeriod): int
    {
        return $this->repository()->count(['quotaPeriod' => $quotaPeriod]);
    }

    public function hasGrantFor(UserId $authorId): bool
    {
        return $this->repository()->count(['authorId' => $authorId->value()]) > 0;
    }

    public function usableOf(UserId $authorId, \DateTimeImmutable $moment): ?OverdraftGrant
    {
        /** @var list<OverdraftGrant> $grants */
        $grants = $this->entityManager->createQueryBuilder()
            ->select('g')
            ->from(OverdraftGrant::class, 'g')
            ->where('g.authorId = :author')
            ->andWhere('g.usedAt IS NULL')
            ->andWhere('g.expiresAt > :moment')
            ->setParameter('author', $authorId->value())
            ->setParameter('moment', $moment)
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();

        return $grants[0] ?? null;
    }

    public function unsettledOf(UserId $authorId): array
    {
        return array_values($this->repository()->findBy([
            'authorId' => $authorId->value(),
            'settledAt' => null,
        ]));
    }

    public function recovery(): array
    {
        /** @var array{granted: int|string, settled: int|string} $row */
        $row = $this->entityManager->createQueryBuilder()
            ->select('COUNT(g.id) AS granted', 'SUM(CASE WHEN g.settledAt IS NULL THEN 0 ELSE 1 END) AS settled')
            ->from(OverdraftGrant::class, 'g')
            // Solo lo que llegó a usarse: una elegibilidad que nadie
            // aprovechó no emitió nada, y contarla hundiría la tasa sin que
            // se hubiera regalado un solo crédito.
            ->where('g.usedAt IS NOT NULL')
            ->getQuery()
            ->getSingleResult();

        return ['granted' => (int) $row['granted'], 'settled' => (int) $row['settled']];
    }

    protected function entityClass(): string
    {
        return OverdraftGrant::class;
    }
}
