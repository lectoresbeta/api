<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Domain\Entity;

use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\ChapterId;

/**
 * The price quoted to one reader when they started correcting one chapter
 * (`FEAT-CRD-009`).
 *
 * **This is not a hold.** Nothing is set aside, the balance does not change,
 * and there is no state to reconcile — that whole machinery was removed in
 * `decision:0006`. It is a quotation: whoever started under certain terms
 * keeps them, even if the author edits the questionnaire afterwards
 * (`RN-7`).
 *
 * The unique key (chapter, reader) is what makes delivering twice harmless.
 */
class CorrectionPrice
{
    private string $chapterId;

    private string $readerId;

    private string $authorId;

    private int $amount;

    private \DateTimeImmutable $quotedAt;

    public function __construct(
        ChapterId $chapterId,
        UserId $readerId,
        UserId $authorId,
        int $amount,
        \DateTimeImmutable $now,
    ) {
        $this->chapterId = $chapterId->value();
        $this->readerId = $readerId->value();
        $this->authorId = $authorId->value();
        $this->amount = $amount;
        $this->quotedAt = $now;
    }

    public function chapterId(): ChapterId
    {
        return ChapterId::fromString($this->chapterId);
    }

    public function readerId(): UserId
    {
        return UserId::fromString($this->readerId);
    }

    public function authorId(): UserId
    {
        return UserId::fromString($this->authorId);
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function quotedAt(): \DateTimeImmutable
    {
        return $this->quotedAt;
    }
}
