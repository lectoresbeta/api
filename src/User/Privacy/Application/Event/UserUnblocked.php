<?php

declare(strict_types=1);

namespace LectoresBeta\User\Privacy\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `UserUnblocked`, tal y como lo modela `User`.
 *
 * Una clase distinta de la que publica `Community`, como siempre
 * ([`decision:0013`](../../../../../docs/decisions/0013-integration-events-travel-without-class-names.md)).
 *
 * La mitad que devuelve las cosas a su sitio: sin ella, levantar un bloqueo
 * no serviría de nada porque la copia seguiría diciendo que lo hay.
 */
final readonly class UserUnblocked implements IncomingIntegrationEvent
{
    private function __construct(
        public string $blockerId,
        public string $blockedId,
        private string $eventId,
        private \DateTimeImmutable $unblockedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'UserUnblocked';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $blockerId = $payload['blockerId'] ?? null;
        $blockedId = $payload['blockedId'] ?? null;

        if (!\is_string($blockerId) || '' === $blockerId || !\is_string($blockedId) || '' === $blockedId) {
            throw new \InvalidArgumentException('UserUnblocked needs both identifiers.');
        }

        return new self($blockerId, $blockedId, $eventId, $occurredAt);
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
        return $this->unblockedAt;
    }

    public function payload(): array
    {
        return [
            'blockerId' => $this->blockerId,
            'blockedId' => $this->blockedId,
            'unblockedAt' => $this->unblockedAt->format(\DATE_ATOM),
        ];
    }
}
