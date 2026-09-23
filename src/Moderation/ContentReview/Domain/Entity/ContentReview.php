<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\ContentReview\Domain\Entity;

use LectoresBeta\Moderation\Claim\Domain\Enum\ClaimTargetType;
use LectoresBeta\Moderation\ContentReview\Domain\Enum\ContentReviewOutcome;
use LectoresBeta\Moderation\ContentReview\Domain\ValueObject\ContentReviewId;

/**
 * The automatic check every text goes through before becoming visible
 * (`FEAT-MOD-011`).
 *
 * Today the checker **approves everything**: it is a port with a null
 * implementation, ready to be replaced by something that actually reads the
 * text. It is built empty on purpose — opening a publication flow later, with
 * works already published and states to invent, costs far more than leaving
 * the hole now.
 *
 * Whether sending unpublished work to an external model is acceptable at all
 * is open (`MOD-37`), and it is the thing the platform exists to guard.
 */
class ContentReview
{
    private string $id;

    private ClaimTargetType $targetType;

    private string $targetId;

    private ContentReviewOutcome $outcome;

    private string $mechanism;

    private string $mechanismVersion;

    private ?string $notes = null;

    private \DateTimeImmutable $reviewedAt;

    public function __construct(
        ContentReviewId $id,
        ClaimTargetType $targetType,
        string $targetId,
        ContentReviewOutcome $outcome,
        string $mechanism,
        string $mechanismVersion,
        \DateTimeImmutable $now,
        ?string $notes = null,
    ) {
        $this->id = $id->value();
        $this->targetType = $targetType;
        $this->targetId = $targetId;
        $this->outcome = $outcome;
        $this->mechanism = $mechanism;
        $this->mechanismVersion = $mechanismVersion;
        $this->reviewedAt = $now;
        $this->notes = $notes;
    }

    public function id(): ContentReviewId
    {
        return ContentReviewId::fromString($this->id);
    }

    public function outcome(): ContentReviewOutcome
    {
        return $this->outcome;
    }

    public function targetId(): string
    {
        return $this->targetId;
    }

    public function reviewedAt(): \DateTimeImmutable
    {
        return $this->reviewedAt;
    }
}
