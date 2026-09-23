<?php

declare(strict_types=1);

namespace LectoresBeta\User\Authentication\Domain\Entity;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Authentication\Domain\ValueObject\RefreshTokenId;

/**
 * The only piece of session state there is (`decision:0007` `RN-2`).
 *
 * The access token is a JWT and cannot be revoked, which is why logging out
 * would mean nothing without this row. Revoking it is what stops the session
 * from renewing itself; the access token still works for up to fifteen
 * minutes, and that window is the accepted cost of the decision.
 *
 * Stored hashed, like every other credential in the system.
 */
class RefreshToken
{
    private string $id;

    private string $userId;

    private string $tokenHash;

    private \DateTimeImmutable $issuedAt;

    private \DateTimeImmutable $expiresAt;

    private ?\DateTimeImmutable $revokedAt = null;

    private ?\DateTimeImmutable $lastUsedAt = null;

    /**
     * Kept so the person can recognise their own sessions in a list. It is
     * not used for anything security-related: a user agent proves nothing.
     */
    private ?string $userAgent = null;

    public function __construct(
        RefreshTokenId $id,
        UserId $userId,
        string $tokenHash,
        \DateTimeImmutable $now,
        \DateTimeImmutable $expiresAt,
        ?string $userAgent = null,
    ) {
        $this->id = $id->value();
        $this->userId = $userId->value();
        $this->tokenHash = $tokenHash;
        $this->issuedAt = $now;
        $this->expiresAt = $expiresAt;
        $this->userAgent = null === $userAgent ? null : mb_substr($userAgent, 0, 255);
    }

    public function id(): RefreshTokenId
    {
        return RefreshTokenId::fromString($this->id);
    }

    public function userId(): UserId
    {
        return UserId::fromString($this->userId);
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function isUsable(\DateTimeImmutable $now): bool
    {
        return null === $this->revokedAt && $now < $this->expiresAt;
    }

    public function markUsed(\DateTimeImmutable $now): void
    {
        $this->lastUsedAt = $now;
    }

    public function revoke(\DateTimeImmutable $now): void
    {
        $this->revokedAt ??= $now;
    }
}
