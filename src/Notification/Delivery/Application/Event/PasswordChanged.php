<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `PasswordChanged`, tal y como lo modela `Notification` (`FEAT-USR-007`
 * `RN-11`, `FEAT-USR-041` `RN-4`).
 *
 * **El aviso que hace que perder una cuenta se note.** Si alguien cambia tu
 * contraseña, este correo es lo único que te lo dice, así que es operativo:
 * ignora toda preferencia, incluido el interruptor general.
 *
 * `viaReset` no decide si se avisa —siempre se avisa— sino **qué dice el
 * aviso**: «has cambiado tu contraseña» y «tu contraseña se ha restablecido»
 * no son la misma frase para quien no hizo ninguna de las dos cosas.
 */
final readonly class PasswordChanged implements IncomingIntegrationEvent
{
    private function __construct(
        public string $userId,
        public bool $viaReset,
        private string $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'PasswordChanged';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $userId = $payload['userId'] ?? null;

        if (!\is_string($userId) || '' === $userId) {
            throw new \InvalidArgumentException('PasswordChanged carries no userId.');
        }

        return new self($userId, true === ($payload['viaReset'] ?? false), $eventId, $occurredAt);
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
            'viaReset' => $this->viaReset,
            'occurredAt' => $this->occurredAt->format(\DATE_ATOM),
        ];
    }
}
