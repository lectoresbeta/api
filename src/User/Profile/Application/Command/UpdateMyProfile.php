<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\Command;

/**
 * Un `PATCH`, y de ahí los dos booleanos.
 *
 * «No enviado» y «enviado vacío» significan cosas distintas aquí —déjalo como
 * está, o bórralo— y un `?string` solo no puede decir las dos. La alternativa
 * sería un `PUT` que obligase a la edición en línea del perfil a reenviar
 * campos que no está tocando.
 */
final readonly class UpdateMyProfile
{
    public function __construct(
        public string $userId,
        public bool $nameGiven,
        public ?string $name,
        public bool $descriptionGiven,
        public ?string $description,
    ) {
    }
}
