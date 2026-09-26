<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Command;

/**
 * «He olvidado mi contraseña» (`FEAT-USR-007`).
 *
 * Solo el correo, y sin sesión: quien no recuerda su contraseña no puede
 * iniciarla, así que exigirla cerraría el único camino de vuelta.
 */
final readonly class RequestPasswordReset
{
    public function __construct(public string $email)
    {
    }
}
