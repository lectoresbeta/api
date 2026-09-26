<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `CreditBalanceChanged`, tal y como lo modela `User` (`FEAT-USR-027`).
 *
 * Se escucha para tener **un número que pintar** en el menú lateral, y no
 * para decidir nada: quien va a gastar créditos pregunta a `Credits`.
 */
final readonly class CreditBalanceChanged implements IncomingIntegrationEvent
{
    private function __construct(
        public string $userId,
        public int $balance,
        private string $eventId,
        private \DateTimeImmutable $changedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'CreditBalanceChanged';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $userId = $payload['userId'] ?? null;
        $balance = $payload['balance'] ?? null;

        if (!\is_string($userId) || '' === $userId || !\is_int($balance)) {
            throw new \InvalidArgumentException('CreditBalanceChanged carries no userId or balance.');
        }

        return new self($userId, $balance, $eventId, $occurredAt);
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
        return $this->changedAt;
    }

    public function payload(): array
    {
        return [
            'userId' => $this->userId,
            'balance' => $this->balance,
            'changedAt' => $this->changedAt->format(\DATE_ATOM),
        ];
    }
}
