<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `CreditDebtFrozen`, tal y como lo modela `Feedback` (`FEAT-CRD-018`
 * `RN-8b`).
 *
 * El autor está cumpliendo una suspensión parcial: sigue debiendo, pero no
 * puede corregir, y corregir es la única forma de saldarlo. Así que durante
 * este plazo lo que ya le entregaron deja de retenerse.
 *
 * **Lo importante es la fecha, no el aviso.** Se guarda, y a partir de ahí
 * cada corrección que llegue pregunta «¿sigue en vigor?» en vez de dar por
 * hecho lo que era cierto el día que llegó el evento.
 */
final readonly class CreditDebtFrozen implements IncomingIntegrationEvent
{
    private function __construct(
        public string $userId,
        public \DateTimeImmutable $frozenUntil,
        private string $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'CreditDebtFrozen';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $userId = $payload['userId'] ?? null;
        $frozenUntil = $payload['frozenUntil'] ?? null;

        if (!\is_string($userId) || '' === $userId || !\is_string($frozenUntil)) {
            throw new \InvalidArgumentException('CreditDebtFrozen needs a user and an end date.');
        }

        $until = \DateTimeImmutable::createFromFormat(\DATE_ATOM, $frozenUntil);

        if (false === $until) {
            throw new \InvalidArgumentException('CreditDebtFrozen carries an unreadable end date.');
        }

        return new self($userId, $until, $eventId, $occurredAt);
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
            'frozenUntil' => $this->frozenUntil->format(\DATE_ATOM),
        ];
    }
}
