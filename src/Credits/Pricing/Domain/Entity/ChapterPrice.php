<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Domain\Entity;

use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Credits\Pricing\Domain\Service\ChapterPricing;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\ChapterId;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\WorkId;

/**
 * What one chapter currently costs to correct (`FEAT-CRD-016`).
 *
 * A read model: it is fed by `WorkContentUpdated` and `QuestionnaireUpdated`
 * and can be rebuilt from scratch by replaying them. It is the source of
 * truth for nothing — which is why a wrong row here is an inconvenience, not
 * a loss of money.
 */
class ChapterPrice
{
    private string $chapterId;

    private string $workId;

    private string $authorId;

    private int $wordCount = 0;

    private int $requiredWords = 0;

    private int $price;

    private \DateTimeImmutable $updatedAt;

    public function __construct(
        ChapterId $chapterId,
        WorkId $workId,
        UserId $authorId,
        int $wordCount,
        int $requiredWords,
        ChapterPricing $pricing,
        \DateTimeImmutable $now,
    ) {
        $this->chapterId = $chapterId->value();
        $this->workId = $workId->value();
        $this->authorId = $authorId->value();
        $this->wordCount = $wordCount;
        $this->requiredWords = $requiredWords;
        $this->price = $pricing->priceOf($wordCount, $requiredWords);
        $this->updatedAt = $now;
    }

    public function chapterId(): ChapterId
    {
        return ChapterId::fromString($this->chapterId);
    }

    public function workId(): WorkId
    {
        return WorkId::fromString($this->workId);
    }

    public function authorId(): UserId
    {
        return UserId::fromString($this->authorId);
    }

    public function price(): int
    {
        return $this->price;
    }

    public function wordCount(): int
    {
        return $this->wordCount;
    }

    public function requiredWords(): int
    {
        return $this->requiredWords;
    }

    public function updateContent(int $wordCount, ChapterPricing $pricing, \DateTimeImmutable $now): void
    {
        $this->wordCount = $wordCount;
        $this->reprice($pricing, $now);
    }

    public function updateQuestionnaire(int $requiredWords, ChapterPricing $pricing, \DateTimeImmutable $now): void
    {
        $this->requiredWords = $requiredWords;
        $this->reprice($pricing, $now);
    }

    private function reprice(ChapterPricing $pricing, \DateTimeImmutable $now): void
    {
        $this->price = $pricing->priceOf($this->wordCount, $this->requiredWords);
        $this->updatedAt = $now;
    }
}
