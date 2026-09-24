<?php

declare(strict_types=1);

namespace LectoresBeta\User\Privacy\Domain\Entity;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Privacy\Domain\Enum\PrivacyAudience;

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

    /**
     * Cuándo se tocaron por última vez. Lo pide la pantalla de ajustes, que
     * dice «guardado» con una fecha.
     */
    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * The three settings that can be spoken about today.
     *
     * `activityVisible` is **not** here, and its absence is deliberate: the
     * label «visibilidad de actividad» does not say what activity is, and the
     * candidates have very different consequences (`S-16`). Offering a switch
     * that nothing reads would be worse than not offering it — somebody would
     * turn it on and believe themselves protected.
     */
    public function change(
        PrivacyAudience $profileVisibility,
        PrivacyAudience $commentPermission,
        PrivacyAudience $messagePermission,
        \DateTimeImmutable $now,
    ): void {
        $this->profileVisibility = $profileVisibility;
        $this->commentPermission = $commentPermission;
        $this->messagePermission = $messagePermission;
        $this->updatedAt = $now;
    }

    /**
     * The settings an account starts with (`RN-4`): **explicit**, and the
     * same three. A missing row is what this method exists to avoid, because
     * «missing» is what somebody eventually reads as «everything allowed».
     */
    public static function defaultsFor(UserId $userId, \DateTimeImmutable $now): self
    {
        return new self($userId, $now);
    }
}
