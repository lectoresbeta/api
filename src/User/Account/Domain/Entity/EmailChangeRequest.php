<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Entity;

use LectoresBeta\User\Account\Domain\ValueObject\Email;
use LectoresBeta\User\Account\Domain\ValueObject\EmailChangeRequestId;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;

/**
 * A pending change of email address (`FEAT-USR-040`).
 *
 * Until it is confirmed, the valid address is still the old one. That is the
 * whole point of the intermediate row: typing the new address must not lock
 * anybody out of their account.
 */
class EmailChangeRequest
{
    private string $id;

    private string $userId;

    private string $newEmail;

    private string $tokenHash;

    private \DateTimeImmutable $requestedAt;

    private \DateTimeImmutable $expiresAt;

    private ?\DateTimeImmutable $consumedAt = null;

    public function __construct(
        EmailChangeRequestId $id,
        UserId $userId,
        Email $newEmail,
        string $tokenHash,
        \DateTimeImmutable $now,
        \DateTimeImmutable $expiresAt,
    ) {
        $this->id = $id->value();
        $this->userId = $userId->value();
        $this->newEmail = $newEmail->value();
        $this->tokenHash = $tokenHash;
        $this->requestedAt = $now;
        $this->expiresAt = $expiresAt;
    }

    public function id(): EmailChangeRequestId
    {
        return EmailChangeRequestId::fromString($this->id);
    }

    public function userId(): UserId
    {
        return UserId::fromString($this->userId);
    }

    public function newEmail(): Email
    {
        return Email::fromString($this->newEmail);
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function isPending(\DateTimeImmutable $now): bool
    {
        return null === $this->consumedAt && $now < $this->expiresAt;
    }

    public function consume(\DateTimeImmutable $now): void
    {
        $this->consumedAt = $now;
    }
}
