<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `CreditBalanceWentNegative`, tal y como lo modela `Feedback`
 * (`FEAT-CRD-018` `RN-9`).
 *
 * Para este contexto significa una cosa concreta: **la corrección que acaba
 * de llegarle a ese autor se entrega bloqueada**. Ve sus metadatos —quién,
 * cuándo, sobre qué capítulo— y no su contenido hasta que reponga.
 *
 * `subjectId` es lo que permite bloquear **esa** y no las que ya había
 * leído (`RN-11`): lo que se ha leído, leído está.
 */
final readonly class CreditBalanceWentNegative implements IncomingIntegrationEvent
{
    private function __construct(
        public string $userId,
        public ?string $subjectId,
        private string $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'CreditBalanceWentNegative';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $userId = $payload['userId'] ?? null;

        $subjectId = $payload['subjectId'] ?? null;

        if (!\is_string($userId) || '' === $userId) {
            throw new \InvalidArgumentException('CreditBalanceWentNegative carries no userId.');
        }

        return new self($userId, \is_string($subjectId) ? $subjectId : null, $eventId, $occurredAt);
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
            'occurredAt' => $this->occurredAt->format(\DATE_ATOM),
        ];
    }
}
