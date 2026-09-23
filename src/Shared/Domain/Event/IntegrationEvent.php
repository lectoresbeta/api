<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Domain\Event;

/**
 * Public asynchronous contract between bounded contexts.
 *
 * An integration event describes **a fact that has already happened** in the
 * context that publishes it. It is never an instruction for another one: the
 * consumer decides what the fact means inside its own model.
 *
 * Rules this interface exists to remember:
 *
 * - it never carries aggregates or Doctrine entities;
 * - it never carries credit amounts unless `Credits` is the publisher;
 * - it never carries private content — work text, corrections, messages —
 *   because a queue with retries and a failure transport is no place for it;
 * - it carries a stable `eventId`, because consumers must be idempotent:
 *   duplicate delivery has to be assumed.
 *
 * It is deliberately a domain interface: marking an event as an integration
 * event must not force anyone to know about Messenger. Routing to RabbitMQ is
 * an Infrastructure concern.
 */
interface IntegrationEvent
{
    /**
     * Stable identifier of the fact. Republishing it does not change it.
     */
    public function eventId(): string;

    /**
     * Name of the fact in business terms: `FeedbackSubmitted`,
     * `AccountActivated`. Never `UpdateCreditsCommand`.
     */
    public function eventName(): string;

    public function occurredAt(): \DateTimeImmutable;

    /**
     * The fact itself, as flat scalars.
     *
     * Flat and scalar on purpose. The payload is a **published contract**
     * read by contexts that do not share a single class with this one, so it
     * has to be inspectable in a queue browser and diffable in a review. A
     * nested structure is where a whole aggregate quietly ends up, which is
     * the thing this interface's docblock forbids.
     *
     * @return array<string, string|int|float|bool|null>
     */
    public function payload(): array;
}
