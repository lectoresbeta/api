<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Entity;

use LectoresBeta\Feedback\Correction\Domain\ValueObject\ChapterId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\WorkId;

/**
 * Whether a chapter is taking corrections, as `Feedback` last heard
 * (`FEAT-CRD-009`).
 *
 * A projection of somebody else's decision. It exists so that opening the
 * correction panel is **one query against this context's own database** and
 * not a round trip to `Credits`: the reader pressed a button and is waiting.
 *
 * It holds a boolean and never a balance or a price. That is the whole point
 * of the arrangement — `Feedback` stays outside the economy and still knows
 * enough to say «ahora mismo no».
 *
 * Being slightly behind is acceptable and was decided so: the worst case is
 * that a correction starts against an author who can no longer pay, and that
 * author ends up in debt — which is already an accepted outcome, and never
 * costs the reader anything.
 */
class CorrectableChapter
{
    private string $chapterId;

    private string $workId;

    private bool $correctable;

    /**
     * When the fact happened, not when it arrived. A queue does not promise
     * order, and an older answer overwriting a newer one would close a
     * chapter that is open.
     */
    private \DateTimeImmutable $changedAt;

    public function __construct(ChapterId $chapterId, WorkId $workId, bool $correctable, \DateTimeImmutable $changedAt)
    {
        $this->chapterId = $chapterId->value();
        $this->workId = $workId->value();
        $this->correctable = $correctable;
        $this->changedAt = $changedAt;
    }

    public function chapterId(): ChapterId
    {
        return ChapterId::fromString($this->chapterId);
    }

    public function isCorrectable(): bool
    {
        return $this->correctable;
    }

    public function changedAt(): \DateTimeImmutable
    {
        return $this->changedAt;
    }

    public function record(bool $correctable, \DateTimeImmutable $changedAt): void
    {
        if ($changedAt < $this->changedAt) {
            return;
        }

        $this->correctable = $correctable;
        $this->changedAt = $changedAt;
    }
}
