<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `SanctionImposed`, tal y como lo modela `Credits` (`FEAT-MOD-006` `RN-9`).
 *
 * De todo lo que trae el hecho, aquí solo importan tres cosas: a quién, de
 * qué tipo y hasta cuándo. El motivo no entra: `Credits` no juzga por qué se
 * sancionó a nadie, solo deja de retenerle lo que ya cobró mientras no puede
 * ganárselo.
 *
 * Una clase distinta de la que publica `Moderation` y de la que modela
 * `User`, a propósito
 * ([`decision:0013`](../../../../../docs/decisions/0013-integration-events-travel-without-class-names.md)).
 */
final readonly class SanctionImposed implements IncomingIntegrationEvent
{
    private function __construct(
        public string $userId,
        public string $type,
        public ?\DateTimeImmutable $expiresAt,
        private string $eventId,
        private \DateTimeImmutable $imposedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'SanctionImposed';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $userId = $payload['userId'] ?? null;
        $type = $payload['type'] ?? null;
        $expiresAt = $payload['expiresAt'] ?? null;

        if (!\is_string($userId) || '' === $userId || !\is_string($type) || '' === $type) {
            throw new \InvalidArgumentException('SanctionImposed needs a user and a type.');
        }

        $ends = \is_string($expiresAt) ? \DateTimeImmutable::createFromFormat(\DATE_ATOM, $expiresAt) : false;

        return new self($userId, $type, false === $ends ? null : $ends, $eventId, $occurredAt);
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
        return $this->imposedAt;
    }

    public function payload(): array
    {
        return [
            'userId' => $this->userId,
            'type' => $this->type,
            'expiresAt' => $this->expiresAt?->format(\DATE_ATOM),
        ];
    }
}
