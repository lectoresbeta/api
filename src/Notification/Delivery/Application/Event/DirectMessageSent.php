<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `DirectMessageSent`, tal y como lo modela `Notification`.
 *
 * **No lleva el mensaje, y aquí es donde más se nota por qué.** Este
 * contexto es el que escribe correos; un hecho que trajera el cuerpo
 * acabaría en un buzón de correo el día que alguien reclasificara este aviso,
 * y una conversación privada habría salido de la plataforma por un canal que
 * su remitente no eligió (`FEAT-COM-011` `RN-9`).
 *
 * Lo que hace falta para pintar el aviso es quién escribió y a qué
 * conversación entrar. Nada más.
 */
final readonly class DirectMessageSent implements IncomingIntegrationEvent
{
    private function __construct(
        public string $conversationId,
        public string $senderId,
        public string $recipientId,
        private string $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'DirectMessageSent';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $conversationId = $payload['conversationId'] ?? null;
        $senderId = $payload['senderId'] ?? null;
        $recipientId = $payload['recipientId'] ?? null;

        if (!\is_string($conversationId) || !\is_string($senderId) || !\is_string($recipientId)) {
            throw new \InvalidArgumentException('DirectMessageSent carries no conversation, sender or recipient.');
        }

        return new self($conversationId, $senderId, $recipientId, $eventId, $occurredAt);
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
            'conversationId' => $this->conversationId,
            'senderId' => $this->senderId,
            'recipientId' => $this->recipientId,
        ];
    }
}
