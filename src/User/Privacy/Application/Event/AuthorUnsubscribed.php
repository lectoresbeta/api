<?php

declare(strict_types=1);

namespace LectoresBeta\User\Privacy\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `AuthorUnsubscribed`, tal y como lo modela `User`.
 *
 * **Una clase distinta de la que publica `Community`**, a propósito
 * ([`decision:0013`](../../../../../docs/decisions/0013-integration-events-travel-without-class-names.md)).
 * Aquí no se importa nada de `Community` y allí nadie sabe que esto existe:
 * lo que comparten es el nombre del hecho y la forma de su payload.
 *
 * Es la mitad que no se puede olvidar: sin ella la copia envejece **hacia el
 * lado peligroso**, con alguien contando como seguidor —y por tanto dentro de
 * una audiencia `FOLLOWERS`— después de haberse ido.
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
