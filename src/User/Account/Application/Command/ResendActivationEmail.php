<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Command;

/**
 * «¿No te ha llegado? Reenviar enlace» (`FEAT-USR-021`).
 *
 * Lleva el correo y no un identificador de sesión porque **se pide sin
 * sesión** (`R-1`): quien cierra el navegador antes de activar no puede
 * entrar —porque no ha activado— y sin esto tampoco podría pedir el reenvío,
 * que es un callejón sin salida con una solución trivial.
 */
final readonly class ResendActivationEmail
{
    public function __construct(public string $email)
    {
    }
}
