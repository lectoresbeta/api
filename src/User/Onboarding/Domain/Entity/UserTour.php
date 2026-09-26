<?php

declare(strict_types=1);

namespace LectoresBeta\User\Onboarding\Domain\Entity;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;

/**
 * Whether somebody has seen a guided tour (`FEAT-USR-026`).
 *
 * One row per tour and not a boolean on `user`: there will be more tours —
 * when a section ships, when something changes — and a column per tour means
 * a migration every time.
 *
 * The key is (user, tour), so the tour identifier is a plain string owned by
 * whoever writes the tour.
 */
class UserTour
{
    private string $userId;

    private string $tourId;

    private ?int $lastStep = null;

    private bool $dismissed = false;

    private ?\DateTimeImmutable $completedAt = null;

    private \DateTimeImmutable $updatedAt;

    public function __construct(UserId $userId, string $tourId, \DateTimeImmutable $now)
    {
        $this->userId = $userId->value();
        $this->tourId = $tourId;
        $this->updatedAt = $now;
    }

    public function userId(): UserId
    {
        return UserId::fromString($this->userId);
    }

    public function tourId(): string
    {
        return $this->tourId;
    }

    public function lastStep(): ?int
    {
        return $this->lastStep;
    }

    public function isFinished(): bool
    {
        return $this->dismissed || null !== $this->completedAt;
    }

    public function advanceTo(int $step, \DateTimeImmutable $now): void
    {
        $this->lastStep = $step;
        $this->updatedAt = $now;
    }

    public function complete(\DateTimeImmutable $now): void
    {
        $this->completedAt ??= $now;
        $this->updatedAt = $now;
    }

    /**
     * Skipping is not completing. Keeping them apart is what lets the product
     * tell «saw it all» from «closed it at the first step».
     */
    public function dismiss(\DateTimeImmutable $now): void
    {
        $this->dismissed = true;
        $this->updatedAt = $now;
    }
}
