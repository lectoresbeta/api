<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Contract;

/**
 * A dónde mandar un correo operativo, y cómo llamar a quien lo recibe.
 *
 * Lo justo para escribir la cabecera y el saludo. **No es una tarjeta de
 * perfil**: no lleva foto ni nada que sirva para pintar a alguien en una
 * pantalla, porque no es para eso.
 */
final readonly class MailingAddress
{
    public function __construct(
        public string $email,
        public string $username,
    ) {
    }
}
