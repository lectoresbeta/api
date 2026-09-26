<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Domain\Entity;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Preferences\Domain\Enum\ContentWarning;

/**
 * One label this person does not want to see (`FEAT-USR-043`).
 *
 * Only exclusions are stored: by default nothing is filtered (`RN-3`). The
 * rows never leave the backend — an author must not be able to work out how
 * many people excluded their work (`RN-7`).
 */
class ContentPreference
{
    private string $userId;

    /**
     * Held as the backed value and handed out as the enum.
     *
     * It is part of the primary key, and Doctrine's XML mapping does not
     * allow an enum on an identifier — the XSD rejects `enum-type` there.
     * Same trade-off the identifiers make, for the same reason.
     */
    private string $warning;

    private \DateTimeImmutable $excludedAt;

    public function __construct(UserId $userId, ContentWarning $warning, \DateTimeImmutable $now)
    {
        $this->userId = $userId->value();
        $this->warning = $warning->value;
        $this->excludedAt = $now;
    }

    public function userId(): UserId
    {
        return UserId::fromString($this->userId);
    }

    public function warning(): ContentWarning
    {
        return ContentWarning::from($this->warning);
    }
}
