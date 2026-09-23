<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\EventProcessing\Domain\Entity;

/**
 * An integration event this context has already applied.
 *
 * Idempotency is a **requirement**, not good manners: RabbitMQ does not
 * guarantee single delivery, and a credit applied twice corrupts the economy
 * in a way no later correction fully repairs (`RN-3`).
 *
 * The row is written **inside the same transaction as the movement**. If it
 * were written after, a crash in between would let the event be applied
 * again; if before, a rollback would lose the movement and mark the event as
 * done.
 */
class ProcessedEvent
{
    private string $eventId;

    private string $eventName;

    private \DateTimeImmutable $processedAt;

    public function __construct(string $eventId, string $eventName, \DateTimeImmutable $now)
    {
        $this->eventId = $eventId;
        $this->eventName = $eventName;
        $this->processedAt = $now;
    }

    public function eventId(): string
    {
        return $this->eventId;
    }

    public function eventName(): string
    {
        return $this->eventName;
    }

    public function processedAt(): \DateTimeImmutable
    {
        return $this->processedAt;
    }
}
