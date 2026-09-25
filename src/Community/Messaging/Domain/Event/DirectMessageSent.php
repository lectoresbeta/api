<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Messaging\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * Alguien le ha escrito a alguien (`FEAT-COM-011` `RN-8`, `RN-9`).
 *
 * **No lleva el mensaje. Ni una línea.** No es una precaución genérica: un
 * hecho que llevara el cuerpo lo sacaría de la plataforma por un canal que su
 * remitente no eligió —una cola que persiste, reintenta y aparca mensajes— y
 * acabaría en un buzón de correo el día que alguien clasificara este aviso
 * como de los que salen por ahí.
 *
 * Lo que viaja es **quién escribió y a qué conversación entrar**, que es
 * exactamente lo que hace falta para pintar el aviso y su enlace.
 */
final readonly class DirectMessageSent implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private string $conversationId,
        private string $senderId,
        private string $recipientId,
        private \DateTimeImmutable $sentAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'DirectMessageSent';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function payload(): array
    {
        return [
            'conversationId' => $this->conversationId,
            'senderId' => $this->senderId,
            'recipientId' => $this->recipientId,
            'sentAt' => $this->sentAt->format(\DATE_ATOM),
        ];
    }
}
