<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Domain\Entity;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;

/**
 * One genre somebody is interested in (`FEAT-USR-023`).
 *
 * It is the same information as the editable literary preferences
 * (`FEAT-USR-009` `RN-6`): one place, not two.
 *
 * Modelled as its own row rather than as a Doctrine association so that the
 * `user` aggregate does not have to load a collection to answer anything, and
 * so that the reverse question — who likes this genre, which the author
 * suggestions need (`FEAT-COM-016`) — is a plain indexed lookup.
 */
class LiteraryPreference
{
    private string $userId;

    private string $genreCode;

    private \DateTimeImmutable $selectedAt;

    public function __construct(UserId $userId, string $genreCode, \DateTimeImmutable $now)
    {
        $this->userId = $userId->value();
        $this->genreCode = strtoupper($genreCode);
        $this->selectedAt = $now;
    }

    public function userId(): UserId
    {
        return UserId::fromString($this->userId);
    }

    public function genreCode(): string
    {
        return $this->genreCode;
    }

    public function selectedAt(): \DateTimeImmutable
    {
        return $this->selectedAt;
    }
}
