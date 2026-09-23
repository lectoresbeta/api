<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Credits\Pricing\Domain\Entity\CorrectionPrice;
use LectoresBeta\Credits\Pricing\Domain\Repository\CorrectionPriceRepository;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\ChapterId;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<CorrectionPrice>
 */
final class DoctrineCorrectionPriceRepository extends DoctrineRepository implements CorrectionPriceRepository
{
    public function save(CorrectionPrice $price): void
    {
        $this->register($price);
    }

    public function quoted(ChapterId $chapterId, UserId $readerId): ?CorrectionPrice
    {
        return $this->repository()->find([
            'chapterId' => $chapterId->value(),
            'readerId' => $readerId->value(),
        ]);
    }

    public function discard(CorrectionPrice $price): void
    {
        $this->forget($price);
    }

    public function openCorrectionsOn(ChapterId $chapterId): int
    {
        return $this->repository()->count(['chapterId' => $chapterId->value()]);
    }

    protected function entityClass(): string
    {
        return CorrectionPrice::class;
    }
}
