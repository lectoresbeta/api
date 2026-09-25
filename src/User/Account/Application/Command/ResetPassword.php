<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Command;

/**
 * Fijar la contraseña con el enlace del correo (`FEAT-USR-007`).
 */
final readonly class ResetPassword
{
    public function __construct(
        public string $token,
        public string $password,
    ) {
    }
}
