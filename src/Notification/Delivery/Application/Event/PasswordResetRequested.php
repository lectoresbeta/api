<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `PasswordResetRequested`, tal y como lo modela `Notification`
 * (`FEAT-USR-007`).
 *
 * Es el mismo trabajo que un alta o un reenvío: pedir un enlace por el
 * contrato de `User` y mandarlo. Trae solo qué cuenta — ni el token, que es
 * una credencial viva, ni la dirección, que se obtiene al enviar.
 */
final readonly class PasswordResetRequested implements IncomingIntegrationEvent
{
    private function __construct(
        public string $userId,
        private string $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'PasswordResetRequested';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $userId = $payload['userId'] ?? null;

        if (!\is_string($userId) || '' === $userId) {
            throw new \InvalidArgumentException('PasswordResetRequested carries no userId.');
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
