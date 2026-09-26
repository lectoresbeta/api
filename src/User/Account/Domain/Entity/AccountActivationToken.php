<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Entity;

use LectoresBeta\User\Account\Domain\ValueObject\AccountActivationTokenId;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;

/**
 * The token behind the activation link (`FEAT-USR-020`).
 *
 * Only its hash is stored. Whoever can read this table must not be able to
 * activate somebody else's account, and an activation link is, for the few
 * hours it lives, as good as a password.
 */
class AccountActivationToken
{
    private string $id;

    private string $userId;

    private string $tokenHash;

    private \DateTimeImmutable $createdAt;

    private \DateTimeImmutable $expiresAt;

    private ?\DateTimeImmutable $usedAt = null;

    private ?\DateTimeImmutable $invalidatedAt = null;

    public function __construct(
        AccountActivationTokenId $id,
        UserId $userId,
        string $tokenHash,
        \DateTimeImmutable $now,
        \DateTimeImmutable $expiresAt,
    ) {
        $this->id = $id->value();
        $this->userId = $userId->value();
        $this->tokenHash = $tokenHash;
        $this->createdAt = $now;
        $this->expiresAt = $expiresAt;
    }

    public function id(): AccountActivationTokenId
    {
        return AccountActivationTokenId::fromString($this->id);
    }

    public function userId(): UserId
    {
        return UserId::fromString($this->userId);
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isUsable(\DateTimeImmutable $now): bool
    {
        return null === $this->usedAt
            && null === $this->invalidatedAt
            && $now < $this->expiresAt;
    }

    public function consume(\DateTimeImmutable $now): void
    {
        $this->usedAt = $now;
    }

    /**
     * Asking for the email again invalidates the previous token
     * (`FEAT-USR-021`): two live links would double the window in which a
     * leaked one still works.
     */
    public function invalidate(\DateTimeImmutable $now): void
    {
        $this->invalidatedAt = $now;
    }
}
