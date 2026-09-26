<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\Credits;

use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Credits\Pricing\Domain\Entity\ChapterPrice;
use LectoresBeta\Credits\Pricing\Domain\Repository\ChapterPriceRepository;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\ChapterId;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\WorkId;

final class InMemoryChapterPrices implements ChapterPriceRepository
{
    /** @var array<string, ChapterPrice> */
    private array $prices = [];

    public function save(ChapterPrice $price): void
    {
        $this->prices[$price->chapterId()->value()] = $price;
    }

    public function ofChapter(ChapterId $chapterId): ?ChapterPrice
    {
        return $this->prices[$chapterId->value()] ?? null;
    }

    public function ofWork(WorkId $workId): array
    {
        return $this->matching(static fn (ChapterPrice $p): bool => $p->workId()->equals($workId));
    }

    public function ofAuthor(UserId $authorId): array
    {
        return $this->matching(static fn (ChapterPrice $p): bool => $p->authorId()->equals($authorId));
    }

    public function countCorrectable(): int
    {
        return \count(array_filter(
            $this->prices,
            static fn (ChapterPrice $price): bool => $price->isCorrectable(),
        ));
    }

    /**
     * @param callable(ChapterPrice): bool $matches
     *
     * @return list<ChapterPrice>
     */
    private function matching(callable $matches): array
    {
        return array_values(array_filter($this->prices, $matches));
    }
}
