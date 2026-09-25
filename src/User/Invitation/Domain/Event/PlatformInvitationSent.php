<?php

declare(strict_types=1);

namespace LectoresBeta\User\Invitation\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * Alguien ha invitado a alguien a la plataforma (`FEAT-USR-018`).
 *
 * **No lleva el token**, y es la regla dura de este proyecto: una credencial
 * viva no entra en una cola que persiste, reintenta y aparca mensajes
 * (`AGENTS.md`). El correo lo pide en el momento de enviarlo, igual que el de
 * activación (`decision:0014`).
 *
 * Sí lleva **la dirección**, porque sin ella no hay a quién escribir y es el
 * único consumidor. Es el mismo trato que `UserRegistered`.
 */
final readonly class PlatformInvitationSent implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private string $invitationId,
        private string $inviterId,
        private string $email,
        private \DateTimeImmutable $sentAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'PlatformInvitationSent';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function payload(): array
    {
        return [
            'invitationId' => $this->invitationId,
            'inviterId' => $this->inviterId,
            'email' => $this->email,
            'sentAt' => $this->sentAt->format(\DATE_ATOM),
        ];
    }
}
