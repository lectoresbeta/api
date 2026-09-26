<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\Credits;

use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Credits\Pricing\Domain\Entity\CorrectionPrice;
use LectoresBeta\Credits\Pricing\Domain\Repository\CorrectionPriceRepository;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\ChapterId;

final class InMemoryCorrectionPrices implements CorrectionPriceRepository
{
    /** @var array<string, CorrectionPrice> */
    private array $quotations = [];

    public function save(CorrectionPrice $price): void
    {
        $this->quotations[self::key($price->chapterId(), $price->readerId())] = $price;
    }

    public function quoted(ChapterId $chapterId, UserId $readerId): ?CorrectionPrice
    {
        return $this->quotations[self::key($chapterId, $readerId)] ?? null;
    }

    public function discard(CorrectionPrice $price): void
    {
        unset($this->quotations[self::key($price->chapterId(), $price->readerId())]);
    }

    public function openCorrectionsOn(ChapterId $chapterId): int
    {
        return \count(array_filter(
            $this->quotations,
            static fn (CorrectionPrice $p): bool => $p->chapterId()->equals($chapterId),
        ));
    }

    private static function key(ChapterId $chapterId, UserId $readerId): string
    {
        return $chapterId->value().'|'.$readerId->value();
    }
}
