<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Review\Domain\Entity;

use LectoresBeta\Moderation\Claim\Domain\ValueObject\ClaimId;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Moderation\Review\Domain\Enum\ReviewDecision;
use LectoresBeta\Moderation\Review\Domain\ValueObject\ClaimReviewId;

/**
 * A moderator's decision on a claim (`FEAT-MOD-002`).
 *
 * The written motivation is mandatory (`RN-4`). A file with no reason cannot
 * be audited and cannot be defended if somebody disputes it — which, with
 * credits and sanctions on the line, eventually happens.
 *
 * The reporter never learns who the moderator was, and neither does the
 * person complained about (`RN-7`). That protects both: whoever reports an
 * abusive user should not be exposed to them, and a moderator should not face
 * reprisals for a decision.
 */
class ClaimReview
{
    private string $id;

    private string $claimId;

    private string $moderatorId;

    private ReviewDecision $decision;

    private string $motivation;

    private \DateTimeImmutable $reviewedAt;

    public function __construct(
        ClaimReviewId $id,
        ClaimId $claimId,
        PartyId $moderatorId,
        ReviewDecision $decision,
        string $motivation,
        \DateTimeImmutable $now,
    ) {
        $motivation = trim($motivation);

        if ('' === $motivation) {
            throw new \DomainException('Every decision carries a written motivation.');
        }

        $this->id = $id->value();
        $this->claimId = $claimId->value();
        $this->moderatorId = $moderatorId->value();
        $this->decision = $decision;
        $this->motivation = $motivation;
        $this->reviewedAt = $now;
    }

    public function id(): ClaimReviewId
    {
        return ClaimReviewId::fromString($this->id);
    }

    public function claimId(): ClaimId
    {
        return ClaimId::fromString($this->claimId);
    }

    public function moderatorId(): PartyId
    {
        return PartyId::fromString($this->moderatorId);
    }

    public function decision(): ReviewDecision
    {
        return $this->decision;
    }

    public function motivation(): string
    {
        return $this->motivation;
    }
}
