<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\EventProcessing\Domain\Entity;

/**
 * An integration event this context has already applied, **per consumer**.
 *
 * Idempotency is a **requirement**, not good manners: RabbitMQ does not
 * guarantee single delivery, and a credit applied twice corrupts the economy
 * in a way no later correction fully repairs (`RN-3`).
 *
 * The row is written **inside the same transaction as the movement**. If it
 * were written after, a crash in between would let the event be applied
 * again; if before, a rollback would lose the movement and mark the event as
 * done.
 *
 * The key is `(eventId, consumer)` and not the event id alone
 * (`FEAT-CRD-011` `RN-4`). With the id alone, the first rule to process a
 * fact would mark it done **for every other rule**, and this context has
 * facts that two independent rules need to see: delivering a correction both
 * moves credits (`FEAT-CRD-006`) and may pay an invitation reward
 * (`FEAT-CRD-005`). The second would never run.
 *
 * `consumer` names the **rule**, not the class that implements it —
 * `welcome-grant`, not a fully qualified name — so renaming a class cannot
 * reopen events that were already applied.
 */
class ProcessedEvent
{
    private string $eventId;

    private string $consumer;

    private string $eventName;

    private \DateTimeImmutable $processedAt;

    public function __construct(string $eventId, string $consumer, string $eventName, \DateTimeImmutable $now)
    {
        $this->eventId = $eventId;
        $this->consumer = $consumer;
        $this->eventName = $eventName;
        $this->processedAt = $now;
    }

    public function eventId(): string
    {
        return $this->eventId;
    }

    public function consumer(): string
    {
        return $this->consumer;
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
