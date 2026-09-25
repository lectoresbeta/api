<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Claim\Domain\Entity;

use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;

/**
 * How much somebody is still allowed to complain (`MOD-2`).
 *
 * Two limits, and they answer two different abuses:
 *
 * - **three claims a month**, which caps the volume;
 * - **cumulative blocking for claiming falsely**: the first dismissed claim
 *   blocks the button for a week, the second for two, the third for three.
 *   Complaining lightly is cheap the first time and expensive the fourth.
 *
 * Whether the strike counter ever resets is open (`MOD-21`): without a reset,
 * a mistake from years ago still weighs.
 */
class ClaimRestriction
{
    public const CLAIMS_PER_MONTH = 3;

    private string $userId;

    private int $dismissedClaims = 0;

    private ?\DateTimeImmutable $blockedUntil = null;

    private \DateTimeImmutable $updatedAt;

    public function __construct(PartyId $userId, \DateTimeImmutable $now)
    {
        $this->userId = $userId->value();
        $this->updatedAt = $now;
    }

    public function userId(): PartyId
    {
        return PartyId::fromString($this->userId);
    }

    public function dismissedClaims(): int
    {
        return $this->dismissedClaims;
    }

    public function isBlockedAt(\DateTimeImmutable $moment): bool
    {
        return null !== $this->blockedUntil && $moment < $this->blockedUntil;
    }

    /**
     * Week one, then two, then three. The block starts from whichever is
     * later — now, or the end of a block still running — so a second strike
     * during a block extends it instead of replacing it.
     */
    public function claimDismissed(\DateTimeImmutable $now): void
    {
        ++$this->dismissedClaims;

        $from = $this->isBlockedAt($now) && null !== $this->blockedUntil ? $this->blockedUntil : $now;

        $this->blockedUntil = $from->modify(\sprintf('+%d weeks', $this->dismissedClaims));
        $this->updatedAt = $now;
    }

    /**
     * Hasta cuándo dura el bloqueo, si lo hay. La pantalla lo necesita:
     * «no puedes reclamar» sin fecha no le dice nada a quien tenía algo
     * legítimo que denunciar.
     */
    public function blockedUntil(): ?\DateTimeImmutable
    {
        return $this->blockedUntil;
    }
}
