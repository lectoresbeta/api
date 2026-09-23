<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Review\Domain\Entity;

use LectoresBeta\Moderation\Claim\Domain\ValueObject\ClaimId;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Moderation\Review\Domain\Enum\MessageAuthorType;
use LectoresBeta\Moderation\Review\Domain\Enum\ThreadParty;
use LectoresBeta\Moderation\Review\Domain\ValueObject\ClaimMessageId;

/**
 * One message in the private conversation around a claim (`FEAT-MOD-009`).
 *
 * `threadParty` decides who may read it. Only the moderator sees both
 * threads; each party sees only their own.
 */
class ClaimMessage
{
    private string $id;

    private string $claimId;

    private ThreadParty $threadParty;

    private MessageAuthorType $authorType;

    private string $authorId;

    private string $body;

    private \DateTimeImmutable $sentAt;

    public function __construct(
        ClaimMessageId $id,
        ClaimId $claimId,
        ThreadParty $threadParty,
        MessageAuthorType $authorType,
        PartyId $authorId,
        string $body,
        \DateTimeImmutable $now,
    ) {
        $this->id = $id->value();
        $this->claimId = $claimId->value();
        $this->threadParty = $threadParty;
        $this->authorType = $authorType;
        $this->authorId = $authorId->value();
        $this->body = trim($body);
        $this->sentAt = $now;
    }

    public function id(): ClaimMessageId
    {
        return ClaimMessageId::fromString($this->id);
    }

    public function claimId(): ClaimId
    {
        return ClaimId::fromString($this->claimId);
    }

    public function threadParty(): ThreadParty
    {
        return $this->threadParty;
    }

    public function authorType(): MessageAuthorType
    {
        return $this->authorType;
    }

    public function body(): string
    {
        return $this->body;
    }

    /**
     * A party reads only their own thread; a moderator reads both.
     */
    public function isReadableBy(ThreadParty $reader, bool $isModerator): bool
    {
        return $isModerator || $reader === $this->threadParty;
    }
}
