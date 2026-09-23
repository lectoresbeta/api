<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Domain\Repository;

use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
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

    /**
     * Every chapter the author has. A balance changes once and the answer to
     * «can this be corrected?» may change for all of them at the same time.
     *
     * @return list<ChapterPrice>
     */
    public function ofAuthor(UserId $authorId): array;
}
