<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Domain\Entity;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;

/**
 * El interruptor general (`FEAT-USR-039` `RN-2`).
 *
 * **Un campo aparte y no un valor de las preferencias individuales**, y esa
 * es toda la razón de que esta clase exista: el interruptor **suspende**, no
 * sobrescribe. Quien lo activa y lo desactiva recupera su configuración tal y
 * como la dejó.
 *
 * Parece un detalle de interfaz y no lo es. Si sobrescribiera, quien lo
 * encendiera y lo apagara volvería con todo silenciado y no sabría por qué
 * dejó de recibir avisos.
 *
 * **No alcanza a los mensajes operativos** (`RN-3`). La pantalla promete
 * silenciarlo todo y no es lo que ocurre; ese texto hay que corregirlo
 * (`S-38`), porque lo que está mal es la promesa, no el comportamiento: sin
 * el correo de activación no hay cuenta usable, y sin el de contraseña nadie
 * recupera la suya.
 */
class UserNotificationSettings
{
    private string $userId;

    private bool $allMuted = false;

    private \DateTimeImmutable $updatedAt;

    public function __construct(UserId $userId, \DateTimeImmutable $now)
    {
        $this->userId = $userId->value();
        $this->updatedAt = $now;
    }

    public function userId(): UserId
    {
        return UserId::fromString($this->userId);
    }

    public function allMuted(): bool
    {
        return $this->allMuted;
    }

    public function muteEverything(bool $muted, \DateTimeImmutable $now): void
    {
        $this->allMuted = $muted;
        $this->updatedAt = $now;
    }
}
