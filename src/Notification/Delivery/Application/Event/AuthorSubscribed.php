<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `AuthorSubscribed`, tal y como lo modela `Notification`.
 *
 * El tercer contexto que escucha este hecho, cada uno con su clase y su
 * lectura ([`decision:0013`](../../../../../docs/decisions/0013-integration-events-travel-without-class-names.md)):
 * `User` resuelve audiencias `FOLLOWERS`, `Community` alimenta sugerencias, y
 * aquí sirve para saber **a quién avisar** cuando ese autor publique
 * (`FEAT-NOT-004`).
 */
final readonly class AuthorSubscribed implements IncomingIntegrationEvent
{
    private function __construct(
        public string $subscriberId,
        public string $authorId,
        private string $eventId,
        private \DateTimeImmutable $subscribedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'AuthorSubscribed';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $subscriberId = $payload['subscriberId'] ?? null;
        $authorId = $payload['authorId'] ?? null;

        if (!\is_string($subscriberId) || '' === $subscriberId || !\is_string($authorId) || '' === $authorId) {
            throw new \InvalidArgumentException('AuthorSubscribed needs both identifiers.');
        }

        return new self($subscriberId, $authorId, $eventId, $occurredAt);
    }

    public function eventId(): string
    {
        return $this->eventId;
    }

    public function eventName(): string
    {
        return self::subscribesTo();
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->subscribedAt;
    }

    public function payload(): array
    {
        return [
            'subscriberId' => $this->subscriberId,
            'authorId' => $this->authorId,
            'subscribedAt' => $this->subscribedAt->format(\DATE_ATOM),
        ];
    }
}
