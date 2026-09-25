<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Enum;

/**
 * El proveedor externo enlazado a la cuenta, si hay alguno.
 *
 * `LOCAL` significa **ninguno**, no «entra con contraseña»: las dos cosas no
 * son la misma desde que una cuenta creada con correo puede enlazar Google
 * después y conservar su contraseña (`FEAT-USR-002` `RN-6`). Quien quiera
 * saber si alguien puede entrar con contraseña mira si tiene hash, que es la
 * pregunta de verdad.
 *
 * `FACEBOOK` and `LINKEDIN` appear in the design but are deferred
 * (`FEAT-USR-019`): they are not listed here because an enum value with no
 * implementation behind it is an invitation to write code for it.
 */
enum AuthProvider: string
{
    case LOCAL = 'LOCAL';
    case GOOGLE = 'GOOGLE';

    /**
     * Signing up with Google means the address is already verified, so there
     * is no activation email to wait for (`OB-11`).
     */
    public function verifiesEmailOnSignUp(): bool
    {
        return self::GOOGLE === $this;
    }
}
