<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Domain\Repository;

use LectoresBeta\Credits\Pricing\Domain\Entity\ChapterPrice;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\ChapterId;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\WorkId;

interface ChapterPriceRepository
{
    public function save(ChapterPrice $price): void;

    public function ofChapter(ChapterId $chapterId): ?ChapterPrice;

    /**
     * @return list<ChapterPrice>
     */
    public function ofWork(WorkId $workId): array;
}
