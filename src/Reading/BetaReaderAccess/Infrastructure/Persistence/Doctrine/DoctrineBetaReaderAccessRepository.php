<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderAccess\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Reading\BetaReaderAccess\Domain\Entity\BetaReaderAccess;
use LectoresBeta\Reading\BetaReaderAccess\Domain\Repository\BetaReaderAccessRepository;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\BetaReaderAccessId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\WorkId;
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

    protected function entityClass(): string
    {
        return BetaReaderAccess::class;
    }
}
