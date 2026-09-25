<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Credits\Pricing\Domain\Entity\ChapterPrice;
use LectoresBeta\Credits\Pricing\Domain\Repository\ChapterPriceRepository;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\ChapterId;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<ChapterPrice>
 */
final class DoctrineChapterPriceRepository extends DoctrineRepository implements ChapterPriceRepository
{
    public function save(ChapterPrice $price): void
    {
        $this->register($price);
    }

    public function ofChapter(ChapterId $chapterId): ?ChapterPrice
    {
        return $this->repository()->find($chapterId->value());
    }

    public function ofWork(WorkId $workId): array
    {
        return array_values($this->repository()->findBy(['workId' => $workId->value()]));
    }

    public function ofAuthor(UserId $authorId): array
    {
        return array_values($this->repository()->findBy(['authorId' => $authorId->value()]));
    }

    public function countCorrectable(): int
    {
        return $this->repository()->count(['correctable' => true]);
    }

    protected function entityClass(): string
    {
        return ChapterPrice::class;
    }
}
