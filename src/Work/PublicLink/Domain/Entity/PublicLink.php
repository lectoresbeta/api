<?php

declare(strict_types=1);

namespace LectoresBeta\Work\PublicLink\Domain\Entity;

use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;
use LectoresBeta\Work\PublicLink\Domain\ValueObject\PublicLinkId;

/**
 * A link that opens a work without a session (`FEAT-FBK-008`).
 *
 * Corrections that arrive through it are **outside the economy**: they
 * neither cost nor pay, and `Credits` does not even consume their event. That
 * is the clearest way to state it — an anonymous corrector has no account to
 * pay into.
 *
 * The token is stored hashed and the link is revocable. Non-enumerable
 * matters here more than elsewhere: the whole point of the platform is that
 * unpublished work stays unpublished.
 */
class PublicLink
{
    private string $id;

    private string $workId;

    private string $tokenHash;

    private ?string $label = null;

    private \DateTimeImmutable $createdAt;

    private ?\DateTimeImmutable $revokedAt = null;

    private ?\DateTimeImmutable $expiresAt = null;

    public function __construct(
        PublicLinkId $id,
        WorkId $workId,
        string $tokenHash,
        \DateTimeImmutable $now,
        ?\DateTimeImmutable $expiresAt = null,
        ?string $label = null,
    ) {
        $this->id = $id->value();
        $this->workId = $workId->value();
        $this->tokenHash = $tokenHash;
        $this->createdAt = $now;
        $this->expiresAt = $expiresAt;
        $this->label = $label;
    }

    public function id(): PublicLinkId
    {
        return PublicLinkId::fromString($this->id);
    }

    public function workId(): WorkId
    {
        return WorkId::fromString($this->workId);
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function isUsableAt(\DateTimeImmutable $moment): bool
    {
        if (null !== $this->revokedAt) {
            return false;
        }

        return null === $this->expiresAt || $moment < $this->expiresAt;
    }

    public function revoke(\DateTimeImmutable $now): void
    {
        $this->revokedAt ??= $now;
    }
}
