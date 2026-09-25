<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `ActivationEmailRequested`, tal y como lo modela `Notification`
 * (`FEAT-USR-021`).
 *
 * Alguien ha pedido que le vuelvan a mandar el enlace. Para este contexto es
 * **el mismo trabajo que un alta**: pedir un enlace nuevo por el contrato de
 * `User` y mandarlo.
 *
 * Trae lo mismo que un alta —qué cuenta— y por la misma razón: ni el token,
 * que es una credencial viva, ni la dirección, que se obtiene al enviar.
 */
final readonly class ActivationEmailRequested implements IncomingIntegrationEvent
{
    private function __construct(
        public string $userId,
        private string $eventId,
        private \DateTimeImmutable $requestedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'ActivationEmailRequested';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $userId = $payload['userId'] ?? null;

        if (!\is_string($userId) || '' === $userId) {
            throw new \InvalidArgumentException('ActivationEmailRequested carries no userId.');
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
        return $this->requestedAt;
    }

    public function payload(): array
    {
        return [
            'userId' => $this->userId,
            'requestedAt' => $this->requestedAt->format(\DATE_ATOM),
        ];
    }
}
