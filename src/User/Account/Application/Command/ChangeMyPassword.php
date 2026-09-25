<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Command;

/**
 * Cambiar o establecer la contraseña (`FEAT-USR-041`).
 *
 * `currentPassword` es **opcional en la forma y obligatorio en casi todos los
 * casos**: solo puede faltar cuando la cuenta no tiene ninguna, que es la de
 * quien entró con Google. Quién decide eso es el servidor, no el cliente.
 */
final readonly class ChangeMyPassword
{
    public function __construct(
        public string $userId,
        public ?string $currentPassword,
        public string $newPassword,
    ) {
    }
}
