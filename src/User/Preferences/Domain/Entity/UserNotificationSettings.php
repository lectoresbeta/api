<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Domain\Entity;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;

/**
 * The master switch (`FEAT-USR-039`).
 *
 * A field of its own and not a value written over every preference row: it
 * **suspends**, it does not overwrite (`RN-2`). Somebody who turns it on and
 * off again gets their configuration back exactly as they left it — which is
 * impossible if flipping the switch rewrites the rows.
 */
class UserNotificationSettings
{
    private string $userId;

    private bool $allMuted = false;

    private \DateTimeImmutable $updatedAt;

    public function __construct(UserId $userId, \DateTimeImmutable $now)
    {
        $this->userId = $userId->value();
        $this->updatedAt = $now;
    }

    public function userId(): UserId
    {
        return UserId::fromString($this->userId);
    }

    public function allMuted(): bool
    {
        return $this->allMuted;
    }

    public function muteAll(\DateTimeImmutable $now): void
    {
        $this->allMuted = true;
        $this->updatedAt = $now;
    }

    public function unmuteAll(\DateTimeImmutable $now): void
    {
        $this->allMuted = false;
        $this->updatedAt = $now;
    }
}
