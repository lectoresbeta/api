<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `CreditDebtThawed`, tal y como lo modela `Feedback` (`FEAT-MOD-006` `RN-9`).
 *
 * Se ha levantado la sanción antes de su plazo, así que la deuda vuelve a
 * retener: lo que se entregue a partir de ahora con el saldo en rojo llega
 * bloqueado otra vez.
 *
 * **Lo ya desbloqueado no se vuelve a bloquear** (`FEAT-CRD-018` `RN-11`). Lo
 * que el autor ha podido leer, leído está, y quitárselo después sería
 * reescribir el pasado.
 */
final readonly class CreditDebtThawed implements IncomingIntegrationEvent
{
    private function __construct(
        public string $userId,
        private string $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'CreditDebtThawed';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $userId = $payload['userId'] ?? null;

        if (!\is_string($userId) || '' === $userId) {
            throw new \InvalidArgumentException('CreditDebtThawed carries no userId.');
        }

        return new self($userId, $eventId, $occurredAt);
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
