<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\Work\Chapter\Domain\Entity\Chapter;
use LectoresBeta\Work\Chapter\Domain\Repository\ChapterRepository;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * @extends DoctrineRepository<Chapter>
 */
final class DoctrineChapterRepository extends DoctrineRepository implements ChapterRepository
{
    public function save(Chapter $chapter): void
    {
        $this->register($chapter);
    }

    public function ofId(ChapterId $id): ?Chapter
    {
        return $this->repository()->find($id->value());
    }

    public function ofWork(WorkId $workId): array
    {
        return array_values($this->repository()->findBy(
            ['workId' => $workId->value()],
            ['position' => 'ASC'],
        ));
    }

    public function wordCountOfWork(WorkId $workId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COALESCE(SUM(c.wordCount), 0)')
            ->from(Chapter::class, 'c')
            ->where('c.workId = :workId')
            ->setParameter('workId', $workId->value())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countOfWork(WorkId $workId): int
    {
        return $this->repository()->count(['workId' => $workId->value()]);
    }

    public function remove(Chapter $chapter): void
    {
        $this->forget($chapter);
    }

    protected function entityClass(): string
    {
        return Chapter::class;
    }
}
