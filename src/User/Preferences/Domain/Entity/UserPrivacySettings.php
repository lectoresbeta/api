<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Domain\Entity;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Preferences\Domain\Enum\PrivacyAudience;

/**
 * Who can see the profile, comment on the texts and send messages
 * (`FEAT-USR-038`).
 *
 * One row per user, written with explicit defaults when the account is
 * created (`RN-4`). A missing row must never be read as «everything
 * allowed»: that is how a privacy setting silently stops applying.
 */
class UserPrivacySettings
{
    private string $userId;

    private PrivacyAudience $profileVisibility;

    private PrivacyAudience $commentPermission;

    private PrivacyAudience $messagePermission;

    private bool $activityVisible;

    private \DateTimeImmutable $updatedAt;

    public function __construct(UserId $userId, \DateTimeImmutable $now)
    {
        $this->userId = $userId->value();
        $this->profileVisibility = PrivacyAudience::EVERYONE;
        $this->commentPermission = PrivacyAudience::EVERYONE;
        $this->messagePermission = PrivacyAudience::EVERYONE;
        $this->activityVisible = true;
        $this->updatedAt = $now;
    }

    public function userId(): UserId
    {
        return UserId::fromString($this->userId);
    }

    public function profileVisibility(): PrivacyAudience
    {
        return $this->profileVisibility;
    }

    public function commentPermission(): PrivacyAudience
    {
        return $this->commentPermission;
    }

    public function messagePermission(): PrivacyAudience
    {
        return $this->messagePermission;
    }

    public function activityVisible(): bool
    {
        return $this->activityVisible;
    }

    public function change(
        PrivacyAudience $profileVisibility,
        PrivacyAudience $commentPermission,
        PrivacyAudience $messagePermission,
        bool $activityVisible,
        \DateTimeImmutable $now,
    ): void {
        $this->profileVisibility = $profileVisibility;
        $this->commentPermission = $commentPermission;
        $this->messagePermission = $messagePermission;
        $this->activityVisible = $activityVisible;
        $this->updatedAt = $now;
    }
}
