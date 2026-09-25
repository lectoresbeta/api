<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Command;

/**
 * Confirmar el cambio desde el enlace del correo nuevo (`FEAT-USR-040`).
 *
 * Solo el token: **no exige sesión**, porque el enlace se abre donde esté
 * abierto ese buzón, que muchas veces es otro dispositivo.
 */
final readonly class ConfirmEmailChange
{
    public function __construct(public string $token)
    {
    }
}
