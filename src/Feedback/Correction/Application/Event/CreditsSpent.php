<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `CreditsSpent`, tal y como lo modela `Feedback` (`FEAT-FBK-010`).
 *
 * Aquí este hecho sirve para **una sola cosa**: poder decirle a quien
 * corrigió cuánto ganó por ello. Nada más de lo que trae se usa, y el saldo
 * de esa persona no se guarda en ningún sitio de este contexto.
 *
 * Llega con el importe porque lo publica `Credits`, que es la única
 * dirección que la regla de aislamiento permite: ningún contexto le manda un
 * importe a `Credits`, y `Credits` puede contar lo que decidió.
 */
final readonly class CreditsSpent implements IncomingIntegrationEvent
{
    private function __construct(
        public string $userId,
        public int $amount,
        public string $reason,
        public ?string $correctionId,
        private string $eventId,
        private \DateTimeImmutable $spentAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'CreditsSpent';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $userId = $payload['userId'] ?? null;
        $amount = $payload['amount'] ?? null;
        $reason = $payload['reason'] ?? null;
        $correctionId = $payload['correctionId'] ?? null;

        if (!\is_string($userId) || !\is_int($amount) || !\is_string($reason)) {
            throw new \InvalidArgumentException('CreditsSpent carries no user, amount or reason.');
        }

        return new self(
            $userId,
            $amount,
            $reason,
            \is_string($correctionId) ? $correctionId : null,
            $eventId,
            $occurredAt,
        );
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
        return $this->spentAt;
    }

    public function payload(): array
    {
        return [
            'userId' => $this->userId,
            'amount' => $this->amount,
            'reason' => $this->reason,
            'correctionId' => $this->correctionId,
            'spentAt' => $this->spentAt->format(\DATE_ATOM),
        ];
    }
}
