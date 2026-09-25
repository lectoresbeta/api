<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;

/**
 * La contraseña de una cuenta ha cambiado (`FEAT-USR-007` `RN-11`,
 * `FEAT-USR-041` `RN-4`).
 *
 * **Un solo hecho para los dos caminos.** Cambiarla desde dentro —con la
 * actual— y desde fuera —con un enlace de correo— acaban en el mismo sitio, y
 * a quien lo consume le da igual cuál fue: lo que tiene que hacer es avisar.
 *
 * `viaReset` es la única distinción que viaja, y no para decidir si se avisa
 * sino **qué dice el aviso**: «has cambiado tu contraseña» y «tu contraseña se
 * ha restablecido» no son la misma frase para quien no hizo ninguna de las
 * dos cosas.
 *
 * Nunca lleva la contraseña ni su hash (`RN-13`).
 */
final readonly class PasswordChanged implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private UserId $userId,
        private bool $viaReset,
        private \DateTimeImmutable $changedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'PasswordChanged';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->changedAt;
    }

    public function payload(): array
    {
        return [
            'userId' => $this->userId->value(),
            'viaReset' => $this->viaReset,
            'changedAt' => $this->changedAt->format(\DATE_ATOM),
        ];
    }
}
