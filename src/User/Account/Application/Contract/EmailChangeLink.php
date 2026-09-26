<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Contract;

/**
 * Lo que hace falta para mandar los **dos** correos de un cambio de dirección
 * (`FEAT-USR-040`).
 *
 * Dos, y no uno: el enlace va a la dirección **nueva** y el aviso a la
 * **anterior**. Ese aviso es la única defensa de quien ha perdido el control
 * de su sesión, así que las dos direcciones viajan juntas — pero en proceso,
 * no por la cola.
 */
final readonly class EmailChangeLink
{
    public function __construct(
        public string $newEmail,
        public string $previousEmail,
        public string $username,
        public string $token,
        public \DateTimeImmutable $expiresAt,
    ) {
    }
}
