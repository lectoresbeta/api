<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `CreditAdjustmentOrdered`, tal y como lo modela `Credits`
 * (`FEAT-MOD-005` `RN-3`).
 *
 * Un administrador ha decidido mover esa cantidad. **Lo que decide este
 * contexto es qué significa**: un movimiento nuevo y nunca una edición —los
 * movimientos son inmutables—, y contabilizado como **grifo** y no como
 * transferencia.
 *
 * Esa segunda parte es la que más fácil se pasa por alto y la que más importa
 * (`RN-4`): un ajuste manual crea o destruye créditos de la nada. Si se
 * contara como transferencia, la invariante contable empezaría a fallar y
 * nadie sabría por qué.
 */
final readonly class CreditAdjustmentOrdered implements IncomingIntegrationEvent
{
    private function __construct(
        public string $userId,
        public int $amount,
        public string $reason,
        public ?string $claimId,
        private string $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'CreditAdjustmentOrdered';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $userId = $payload['userId'] ?? null;
        $amount = $payload['amount'] ?? null;
        $reason = $payload['reason'] ?? null;
        $claimId = $payload['claimId'] ?? null;

        if (!\is_string($userId) || '' === $userId) {
            throw new \InvalidArgumentException('CreditAdjustmentOrdered carries no user.');
        }

        if (!\is_int($amount) || 0 === $amount) {
            throw new \InvalidArgumentException('CreditAdjustmentOrdered carries no amount.');
        }

        if (!\is_string($reason) || '' === $reason) {
            throw new \InvalidArgumentException('CreditAdjustmentOrdered carries no reason.');
        }

        return new self(
            $userId,
            $amount,
            $reason,
            \is_string($claimId) && '' !== $claimId ? $claimId : null,
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
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'userId' => $this->userId,
            'amount' => $this->amount,
            'reason' => $this->reason,
            'claimId' => $this->claimId,
            'occurredAt' => $this->occurredAt->format(\DATE_ATOM),
        ];
    }
}
