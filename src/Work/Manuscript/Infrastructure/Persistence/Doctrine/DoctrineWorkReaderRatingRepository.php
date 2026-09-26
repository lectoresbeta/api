<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\Work\Manuscript\Domain\Entity\WorkReaderRating;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkReaderRatingRepository;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * @extends DoctrineRepository<WorkReaderRating>
 */
final class DoctrineWorkReaderRatingRepository extends DoctrineRepository implements WorkReaderRatingRepository
{
    public function save(WorkReaderRating $rating): void
    {
        $this->register($rating);
    }

    public function between(WorkId $workId, string $readerId): ?WorkReaderRating
    {
        return $this->repository()->find(['workId' => $workId->value(), 'readerId' => $readerId]);
    }

    protected function entityClass(): string
    {
        return WorkReaderRating::class;
    }
}
