<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\AuditLog\Domain\Entity;

use LectoresBeta\Moderation\AuditLog\Domain\ValueObject\AuditEntryId;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;

/**
 * Every administrative action, recorded (`RN-5`, `MOD-36`).
 *
 * Immutable and insert only: there is no mutator here, and the repository
 * offers no update. A log that can be edited is not a log.
 *
 * `payload` holds the before and after of whatever changed. It must never
 * carry the text of a work, a correction or a private message — the log says
 * what was done, not what was written.
 */
class AuditEntry
{
    private string $id;

    private string $actorId;

    private string $action;

    private string $targetType;

    private string $targetId;

    private ?string $reason = null;

    /** @var array<string, mixed> */
    private array $payload = [];

    private \DateTimeImmutable $occurredAt;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        AuditEntryId $id,
        PartyId $actorId,
        string $action,
        string $targetType,
        string $targetId,
        \DateTimeImmutable $now,
        ?string $reason = null,
        array $payload = [],
    ) {
        $this->id = $id->value();
        $this->actorId = $actorId->value();
        $this->action = $action;
        $this->targetType = $targetType;
        $this->targetId = $targetId;
        $this->occurredAt = $now;
        $this->reason = $reason;
        $this->payload = $payload;
    }

    public function id(): AuditEntryId
    {
        return AuditEntryId::fromString($this->id);
    }

    public function actorId(): PartyId
    {
        return PartyId::fromString($this->actorId);
    }

    public function action(): string
    {
        return $this->action;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
