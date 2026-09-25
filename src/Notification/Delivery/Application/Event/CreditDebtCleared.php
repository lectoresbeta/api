<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

final readonly class CreditDebtCleared implements IncomingIntegrationEvent
{
    private function __construct(
        public string $userId,
        public int $balance,
        private string $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'CreditDebtCleared';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $userId = $payload['userId'] ?? null;
        $balance = $payload['balance'] ?? null;

        if (!\is_string($userId) || '' === $userId || !\is_int($balance)) {
            throw new \InvalidArgumentException('CreditDebtCleared carries no userId or balance.');
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
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'userId' => $this->userId,
            'balance' => $this->balance,
            'occurredAt' => $this->occurredAt->format(\DATE_ATOM),
        ];
    }
}
