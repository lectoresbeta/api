<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderAccess\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Reading\BetaReaderAccess\Domain\Entity\BetaReaderAccess;
use LectoresBeta\Reading\BetaReaderAccess\Domain\Repository\BetaReaderAccessRepository;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\BetaReaderAccessId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Domain\Pagination\Cursor;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<BetaReaderAccess>
 */
final class DoctrineBetaReaderAccessRepository extends DoctrineRepository implements BetaReaderAccessRepository
{
    public function save(BetaReaderAccess $access): void
    {
        $this->register($access);
    }

    public function ofId(BetaReaderAccessId $id): ?BetaReaderAccess
    {
        return $this->repository()->find($id->value());
    }

    public function liveFor(ReaderId $readerId, WorkId $workId): ?BetaReaderAccess
    {
        return $this->repository()->findOneBy([
            'readerId' => $readerId->value(),
            'workId' => $workId->value(),
            'revokedAt' => null,
        ]);
    }

    public function liveOnWork(WorkId $workId): array
    {
        return array_values($this->repository()->findBy([
            'workId' => $workId->value(),
            'revokedAt' => null,
        ]));
    }

    public function liveOfReader(ReaderId $readerId): array
    {
        return array_values($this->repository()->findBy([
            'readerId' => $readerId->value(),
            'revokedAt' => null,
        ]));
    }

    public function grantedByEvent(string $eventId): ?BetaReaderAccess
    {
        return $this->repository()->findOneBy(['grantedByEventId' => $eventId]);
    }

    public function livePageOnWork(WorkId $workId, ?Cursor $after, int $limit): array
    {
        $query = $this->repository()->createQueryBuilder('a')
            ->where('a.workId = :work')
            ->andWhere('a.revokedAt IS NULL')
            ->setParameter('work', $workId->value());

        if (null !== $after) {
            $query
                ->andWhere('(a.grantedAt < :at OR (a.grantedAt = :at AND a.id < :id))')
                ->setParameter('at', $after->at)
                ->setParameter('id', $after->id);
        }

        /** @var list<BetaReaderAccess> $found */
        $found = $query
            ->orderBy('a.grantedAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setMaxResults($limit + 1)
            ->getQuery()
            ->getResult();

        return $found;
    }

    protected function entityClass(): string
    {
        return BetaReaderAccess::class;
    }
}
