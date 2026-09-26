<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Domain\Event;

/**
 * An integration event as the **consuming** context models it.
 *
 * This is the interface that makes bounded-context isolation survive contact
 * with a message queue. `Credits` has to react to a fact published by `User`,
 * and it must do so without importing a single class of `User` — so both
 * sides declare their own class for the same fact, and the only thing they
 * share is the **name** of the fact and the shape of its payload.
 *
 * What travels on the wire is therefore never a PHP class. It is a name, an
 * identifier and a flat payload; each side rebuilds whatever object suits it.
 * A consumer that needs two of the published fields simply declares two.
 *
 * The cost is honest and worth stating: **nothing checks at compile time that
 * the two sides agree.** The event catalogue in `docs/events/` is the
 * contract, and a consumer that reads a field the publisher stopped sending
 * finds out at runtime. That is the price of not sharing a model, and it is
 * cheaper than the coupling it avoids.
 */
interface IncomingIntegrationEvent extends IntegrationEvent
{
    /**
     * The business name of the fact, as the publisher writes it:
     * `AccountActivated`, never a class name.
     */
    public static function subscribesTo(): string;

    /**
     * @param array<string, string|int|float|bool|list<string|int|float|bool>|null> $payload
     */
    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self;
}
