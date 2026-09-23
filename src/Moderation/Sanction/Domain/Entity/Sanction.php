<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Sanction\Domain\Entity;

use LectoresBeta\Moderation\Claim\Domain\ValueObject\ClaimId;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Moderation\Sanction\Domain\Enum\SanctionType;
use LectoresBeta\Moderation\Sanction\Domain\Enum\SuspensionDuration;
use LectoresBeta\Moderation\Sanction\Domain\ValueObject\SanctionId;

/**
 * A measure imposed on somebody (`FEAT-MOD-006`).
 *
 * `Moderation` records it and publishes `SanctionImposed`; **`User` is what
 * applies it**. This context never writes to another context's tables.
 *
 * A partial suspension freezes the person's debt (`MOD-43`): they cannot
 * correct, and correcting is the only way to clear it. Charging for a
 * suspension somebody cannot work off would be a penalty nobody decided.
 */
class Sanction
{
    private string $id;

    private string $userId;

    private SanctionType $type;

    private ?SuspensionDuration $duration = null;

    private string $reason;

    private ?string $claimId = null;

    private string $imposedBy;

    private \DateTimeImmutable $imposedAt;

    private ?\DateTimeImmutable $expiresAt = null;

    private ?\DateTimeImmutable $liftedAt = null;

    public function __construct(
        SanctionId $id,
        PartyId $userId,
        SanctionType $type,
        string $reason,
        PartyId $imposedBy,
        \DateTimeImmutable $now,
        ?SuspensionDuration $duration = null,
        ?ClaimId $claimId = null,
    ) {
        if ($type->isTemporary() && null === $duration) {
            throw new \DomainException('A partial suspension always has a duration.');
        }

        $this->id = $id->value();
        $this->userId = $userId->value();
        $this->type = $type;
        $this->duration = $duration;
        $this->reason = trim($reason);
        $this->imposedBy = $imposedBy->value();
        $this->imposedAt = $now;
        $this->claimId = $claimId?->value();
        $this->expiresAt = $duration?->endingFrom($now);
    }

    public function id(): SanctionId
    {
        return SanctionId::fromString($this->id);
    }

    public function userId(): PartyId
    {
        return PartyId::fromString($this->userId);
    }

    public function type(): SanctionType
    {
        return $this->type;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function isInForceAt(\DateTimeImmutable $moment): bool
    {
        if (null !== $this->liftedAt) {
            return false;
        }

        return null === $this->expiresAt || $moment < $this->expiresAt;
    }

    /**
     * Temporary sanctions expire on their own; this is for lifting one early,
     * which is itself an auditable administrative action.
     */
    public function lift(\DateTimeImmutable $now): void
    {
        $this->liftedAt ??= $now;
    }
}
