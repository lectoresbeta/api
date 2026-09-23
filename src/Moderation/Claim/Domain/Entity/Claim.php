<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Claim\Domain\Entity;

use LectoresBeta\Moderation\Claim\Domain\Enum\ClaimReason;
use LectoresBeta\Moderation\Claim\Domain\Enum\ClaimStatus;
use LectoresBeta\Moderation\Claim\Domain\Enum\ClaimTargetType;
use LectoresBeta\Moderation\Claim\Domain\Enum\ClaimType;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\ClaimId;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;

/**
 * A complaint about somebody else's content (`FEAT-MOD-001`).
 *
 * **Submitting one has no immediate effect** (`RN-1`): nothing is hidden, no
 * credits are frozen and the person complained about is not told. The
 * opposite would turn the report button into a weapon.
 *
 * It has a cost worth stating plainly: genuinely harmful content stays up
 * until somebody reviews it.
 *
 * The other half of the same design is that **credits do not move until a
 * moderator upholds the claim**. There is no intermediate state where the
 * money is in the air, so complaining is never profitable on its own.
 */
class Claim
{
    private string $id;

    private ClaimType $type;

    private ClaimTargetType $targetType;

    private string $targetId;

    private string $reporterId;

    /** Whoever the claim is about, when it is known at submission time. */
    private ?string $subjectId = null;

    private ClaimReason $reason;

    private ?string $description = null;

    private ClaimStatus $status;

    /**
     * True when a moderator entered it on somebody's behalf, by email,
     * because their button was blocked (`MOD-22`). It still consumes their
     * quota (`MOD-45`) — otherwise email would be the way around the limit.
     */
    private bool $filedOnBehalf = false;

    private \DateTimeImmutable $submittedAt;

    private ?\DateTimeImmutable $resolvedAt = null;

    public function __construct(
        ClaimId $id,
        ClaimType $type,
        ClaimTargetType $targetType,
        string $targetId,
        PartyId $reporterId,
        ClaimReason $reason,
        \DateTimeImmutable $now,
        ?PartyId $subjectId = null,
        ?string $description = null,
        bool $filedOnBehalf = false,
    ) {
        $this->id = $id->value();
        $this->type = $type;
        $this->targetType = $targetType;
        $this->targetId = $targetId;
        $this->reporterId = $reporterId->value();
        $this->subjectId = $subjectId?->value();
        $this->reason = $reason;
        $this->description = $description;
        $this->status = ClaimStatus::PENDING;
        $this->submittedAt = $now;
        $this->filedOnBehalf = $filedOnBehalf;
    }

    public function id(): ClaimId
    {
        return ClaimId::fromString($this->id);
    }

    public function type(): ClaimType
    {
        return $this->type;
    }

    public function targetType(): ClaimTargetType
    {
        return $this->targetType;
    }

    public function targetId(): string
    {
        return $this->targetId;
    }

    public function reporterId(): PartyId
    {
        return PartyId::fromString($this->reporterId);
    }

    public function subjectId(): ?PartyId
    {
        return null === $this->subjectId ? null : PartyId::fromString($this->subjectId);
    }

    public function status(): ClaimStatus
    {
        return $this->status;
    }

    public function submittedAt(): \DateTimeImmutable
    {
        return $this->submittedAt;
    }

    /**
     * Nobody moderates a matter they are part of (`RN-3`): not their work,
     * not their correction, and not a claim they filed or that points at
     * them.
     */
    public function canBeReviewedBy(PartyId $moderator): bool
    {
        return $moderator->value() !== $this->reporterId
            && $moderator->value() !== $this->subjectId;
    }

    public function takeUnderReview(\DateTimeImmutable $now): void
    {
        if (ClaimStatus::PENDING !== $this->status) {
            return;
        }

        $this->status = ClaimStatus::UNDER_REVIEW;
    }

    public function uphold(\DateTimeImmutable $now): void
    {
        $this->close(ClaimStatus::UPHELD, $now);
    }

    public function reject(\DateTimeImmutable $now): void
    {
        $this->close(ClaimStatus::REJECTED, $now);
    }

    /**
     * The target no longer exists, so there is nothing to decide. Not a
     * decision and not a reversal: the file simply stops being open.
     */
    public function archive(\DateTimeImmutable $now): void
    {
        $this->close(ClaimStatus::ARCHIVED, $now);
    }

    private function close(ClaimStatus $status, \DateTimeImmutable $now): void
    {
        if (!$this->status->isOpen()) {
            return;
        }

        $this->status = $status;
        $this->resolvedAt = $now;
    }
}
