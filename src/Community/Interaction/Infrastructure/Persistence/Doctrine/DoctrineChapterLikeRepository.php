<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Community\Interaction\Domain\Entity\ChapterLike;
use LectoresBeta\Community\Interaction\Domain\Repository\ChapterLikeRepository;
use LectoresBeta\Community\Interaction\Domain\ValueObject\ChapterId;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<ChapterLike>
 */
final class DoctrineChapterLikeRepository extends DoctrineRepository implements ChapterLikeRepository
{
    public function between(ChapterId $chapterId, MemberId $memberId): ?ChapterLike
    {
        return $this->repository()->find([
            'chapterId' => $chapterId->value(),
            'memberId' => $memberId->value(),
        ]);
    }

    public function save(ChapterLike $like): void
    {
        $this->register($like);
    }

    public function remove(ChapterLike $like): void
    {
        $this->forget($like);
    }

    protected function entityClass(): string
    {
        return ChapterLike::class;
    }
}
