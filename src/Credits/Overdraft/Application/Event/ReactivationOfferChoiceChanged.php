<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Overdraft\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `ReactivationOfferChoiceChanged`, tal y como lo modela `Credits`
 * (`FEAT-CRD-019` `RN-2d`, `RN-8`).
 *
 * Lo que este contexto entiende del hecho es una sola cosa: **si puede
 * seleccionar a esa persona**. No le interesa qué pantalla se tocó ni qué
 * canales existen; eso es de quien lo publica.
 */
final readonly class ReactivationOfferChoiceChanged implements IncomingIntegrationEvent
{
    private function __construct(
        public string $userId,
        public bool $accepted,
        private string $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'ReactivationOfferChoiceChanged';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $userId = $payload['userId'] ?? null;
        $accepted = $payload['accepted'] ?? null;

        if (!\is_string($userId) || '' === $userId) {
            throw new \InvalidArgumentException('ReactivationOfferChoiceChanged carries no user.');
        }

        if (!\is_bool($accepted)) {
            throw new \InvalidArgumentException('ReactivationOfferChoiceChanged carries no answer.');
        }

        return new self($userId, $accepted, $eventId, $occurredAt);
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
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'userId' => $this->userId,
            'accepted' => $this->accepted,
            'occurredAt' => $this->occurredAt->format(\DATE_ATOM),
        ];
    }
}
