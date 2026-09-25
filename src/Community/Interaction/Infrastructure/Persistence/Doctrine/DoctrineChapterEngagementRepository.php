<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Community\Interaction\Domain\Entity\ChapterEngagement;
use LectoresBeta\Community\Interaction\Domain\Repository\ChapterEngagementRepository;
use LectoresBeta\Community\Interaction\Domain\ValueObject\ChapterId;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<ChapterEngagement>
 */
final class DoctrineChapterEngagementRepository extends DoctrineRepository implements ChapterEngagementRepository
{
    public function ofChapter(ChapterId $chapterId): ?ChapterEngagement
    {
        return $this->repository()->find($chapterId->value());
    }

    public function save(ChapterEngagement $engagement): void
    {
        $this->register($engagement);
    }

    protected function entityClass(): string
    {
        return ChapterEngagement::class;
    }
}
