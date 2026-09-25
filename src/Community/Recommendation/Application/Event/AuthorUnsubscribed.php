<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Recommendation\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `AuthorUnsubscribed`, tal y como lo modela la proyección de autores.
 *
 * Lo publica el mismo contexto —`Community/Subscription`— y aun así viaja
 * por la cola y se vuelve a modelar aquí. No es ceremonia: la proyección
 * tiene que poder reconstruirse desde los hechos, y un contador que se
 * actualizara con una llamada directa desde el caso de uso quedaría
 * irreparable en cuanto se perdiera una.
 */
final readonly class AuthorUnsubscribed implements IncomingIntegrationEvent
{
    private function __construct(
        public string $subscriberId,
        public string $authorId,
        private string $eventId,
        private \DateTimeImmutable $happenedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'AuthorUnsubscribed';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $subscriberId = $payload['subscriberId'] ?? null;
        $authorId = $payload['authorId'] ?? null;

        if (!\is_string($subscriberId) || '' === $subscriberId || !\is_string($authorId) || '' === $authorId) {
            throw new \InvalidArgumentException('AuthorUnsubscribed needs both identifiers.');
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
        return $this->happenedAt;
    }

    public function payload(): array
    {
        return ['subscriberId' => $this->subscriberId, 'authorId' => $this->authorId];
    }
}
