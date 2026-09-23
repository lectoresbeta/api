<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\ModeratorRole\Domain\Entity;

use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Moderation\ModeratorRole\Domain\Enum\ModeratorLevel;

/**
 * Somebody who may moderate (`FEAT-MOD-004`).
 *
 * `emailAlerts` decides who gets warned when a claim arrives. Those warnings
 * are **operational** (`RN-8`): they go to whoever holds the role and has
 * them on, and they ignore the person's general notification preferences,
 * because they are part of a job rather than of a feed.
 */
class ModeratorRole
{
    private string $userId;

    private ModeratorLevel $level;

    private bool $emailAlerts = true;

    private string $grantedBy;

    private \DateTimeImmutable $grantedAt;

    private ?\DateTimeImmutable $revokedAt = null;

    public function __construct(
        PartyId $userId,
        ModeratorLevel $level,
        PartyId $grantedBy,
        \DateTimeImmutable $now,
    ) {
        $this->userId = $userId->value();
        $this->level = $level;
        $this->grantedBy = $grantedBy->value();
        $this->grantedAt = $now;
    }

    public function userId(): PartyId
    {
        return PartyId::fromString($this->userId);
    }

    public function level(): ModeratorLevel
    {
        return $this->level;
    }

    public function isActive(): bool
    {
        return null === $this->revokedAt;
    }

    public function wantsEmailAlerts(): bool
    {
        return $this->emailAlerts && $this->isActive();
    }

    public function setEmailAlerts(bool $enabled): void
    {
        $this->emailAlerts = $enabled;
    }

    public function promoteTo(ModeratorLevel $level): void
    {
        $this->level = $level;
    }

    public function revoke(\DateTimeImmutable $now): void
    {
        $this->revokedAt ??= $now;
    }
}
