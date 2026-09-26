<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Feedback\Correction\Domain\Entity\CorrectableChapter;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectableChapterRepository;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\ChapterId;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<CorrectableChapter>
 */
final class DoctrineCorrectableChapterRepository extends DoctrineRepository implements CorrectableChapterRepository
{
    public function save(CorrectableChapter $chapter): void
    {
        $this->register($chapter);
    }

    public function ofChapter(ChapterId $chapterId): ?CorrectableChapter
    {
        return $this->repository()->find($chapterId->value());
    }

    protected function entityClass(): string
    {
        return CorrectableChapter::class;
    }
}
