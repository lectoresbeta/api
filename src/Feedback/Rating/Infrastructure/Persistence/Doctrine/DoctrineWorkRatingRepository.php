<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Rating\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Feedback\Correction\Domain\ValueObject\ReaderId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\WorkId;
use LectoresBeta\Feedback\Rating\Domain\Entity\WorkRating;
use LectoresBeta\Feedback\Rating\Domain\Repository\WorkRatingRepository;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<WorkRating>
 */
final class DoctrineWorkRatingRepository extends DoctrineRepository implements WorkRatingRepository
{
    public function between(ReaderId $readerId, WorkId $workId): ?WorkRating
    {
        return $this->repository()->findOneBy([
            'readerId' => $readerId->value(),
            'workId' => $workId->value(),
        ]);
    }

    public function save(WorkRating $rating): void
    {
        $this->register($rating);
    }

    protected function entityClass(): string
    {
        return WorkRating::class;
    }
}
