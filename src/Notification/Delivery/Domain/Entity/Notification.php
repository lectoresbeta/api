<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Domain\Entity;

use LectoresBeta\Notification\Delivery\Domain\Enum\NotificationKind;
use LectoresBeta\Notification\Delivery\Domain\ValueObject\NotificationId;
use LectoresBeta\Notification\Delivery\Domain\ValueObject\RecipientId;

/**
 * One notice for one person.
 *
 * `sourceEventId` is what makes delivery idempotent (`RN-3`): the same
 * integration event, delivered twice by RabbitMQ, must not produce two
 * identical notices. The unique index on (recipient, kind, source event) is
 * where that is actually enforced.
 *
 * `payload` carries what the template needs to build a sentence and a link —
 * names, titles, counts. **Never the content of a work, a correction or a
 * message** (`RN-4`): unpublished writing does not leave the platform by
 * email.
 */
class Notification
{
    private string $id;

    private string $recipientId;

    private NotificationKind $kind;

    /** @var array<string, scalar|null> */
    private array $payload = [];

    private ?string $sourceEventId = null;

    private \DateTimeImmutable $createdAt;

    private ?\DateTimeImmutable $readAt = null;

    /**
     * @param array<string, scalar|null> $payload
     */
    public function __construct(
        NotificationId $id,
        RecipientId $recipientId,
        NotificationKind $kind,
        \DateTimeImmutable $now,
        array $payload = [],
        ?string $sourceEventId = null,
    ) {
        $this->id = $id->value();
        $this->recipientId = $recipientId->value();
        $this->kind = $kind;
        $this->createdAt = $now;
        $this->payload = $payload;
        $this->sourceEventId = $sourceEventId;
    }

    public function id(): NotificationId
    {
        return NotificationId::fromString($this->id);
    }

    public function recipientId(): RecipientId
    {
        return RecipientId::fromString($this->recipientId);
    }

    public function kind(): NotificationKind
    {
        return $this->kind;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    public function isRead(): bool
    {
        return null !== $this->readAt;
    }

    public function markRead(\DateTimeImmutable $now): void
    {
        $this->readAt ??= $now;
    }
}
