<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `EmailChangeRequested`, tal y como lo modela `Notification`
 * (`FEAT-USR-040`).
 *
 * Trae **qué solicitud**, no qué direcciones. Las dos —la nueva, a la que va
 * el enlace, y la anterior, a la que va el aviso— se piden por el contrato
 * publicado al enviar: un evento que reparte direcciones de correo por la
 * cola es una filtración esperando a ocurrir.
 */
final readonly class EmailChangeRequested implements IncomingIntegrationEvent
{
    private function __construct(
        public string $userId,
        public string $requestId,
        private string $eventId,
        private \DateTimeImmutable $requestedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'EmailChangeRequested';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $userId = $payload['userId'] ?? null;
        $requestId = $payload['requestId'] ?? null;

        if (!\is_string($userId) || !\is_string($requestId) || '' === $userId || '' === $requestId) {
            throw new \InvalidArgumentException('EmailChangeRequested carries no userId or requestId.');
        }

        return new self($userId, $requestId, $eventId, $occurredAt);
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
            'requestId' => $this->requestId,
            'requestedAt' => $this->requestedAt->format(\DATE_ATOM),
        ];
    }
}
