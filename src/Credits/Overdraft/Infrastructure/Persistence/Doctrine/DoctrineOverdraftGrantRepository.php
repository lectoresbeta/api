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

    public function countInPeriod(UserId $authorId, string $quotaPeriod): int
    {
        return $this->repository()->count([
            'authorId' => $authorId->value(),
            'quotaPeriod' => $quotaPeriod,
        ]);
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
            ->getQuery()
            ->getSingleResult();

        return ['granted' => (int) $row['granted'], 'settled' => (int) $row['settled']];
    }

    protected function entityClass(): string
    {
        return OverdraftGrant::class;
    }
}
