<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `AuthorUnsubscribed`, tal y como lo modela `Notification`.
 *
 * **Es la mitad que se olvida**, y la que envejece hacia el lado peligroso:
 * sin ella, alguien que dejó de seguir a un autor seguiría recibiendo avisos
 * de todo lo que publique. Un aviso que no se puede apagar dejando de seguir
 * es peor que no haberlo mandado nunca.
 *
 * Llega también cuando alguien bloquea a alguien: el bloqueo deshace los dos
 * seguimientos y publica un `AuthorUnsubscribed` por cada uno
 * (`FEAT-COM-034` `RN-3`). Esta copia se cura sola, sin saber que hubo
 * bloqueo.
 */
final readonly class AuthorUnsubscribed implements IncomingIntegrationEvent
{
    private function __construct(
        public string $subscriberId,
        public string $authorId,
        private string $eventId,
        private \DateTimeImmutable $unsubscribedAt,
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
        return $this->unsubscribedAt;
    }

    public function payload(): array
    {
        return [
            'subscriberId' => $this->subscriberId,
            'authorId' => $this->authorId,
            'unsubscribedAt' => $this->unsubscribedAt->format(\DATE_ATOM),
        ];
    }
}
