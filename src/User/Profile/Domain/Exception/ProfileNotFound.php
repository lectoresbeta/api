<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * No hay perfil ahí (`FEAT-USR-014` `RN-7`, `FEAT-USR-035` `RN-8`).
 *
 * **Cinco situaciones y una sola respuesta**: el nombre no existe, el alias
 * caducó, el alias es de una cuenta eliminada, la cuenta está eliminada, o su
 * dueño ha restringido el perfil.
 *
 * La última es la que obliga a que sean la misma. Un `403` confirmaría que la
 * cuenta existe, y para un ajuste cuya razón de ser es **no ser encontrado**
 * eso lo deja sin efecto: quien quisiera comprobar si alguien está en la
 * plataforma solo tendría que leer el código de estado.
 */
final class ProfileNotFound extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('There is no profile there.');
    }

    public function errorCode(): string
    {
        return 'PROFILE_NOT_FOUND';
    }

    public function kind(): FailureKind
    {
        return FailureKind::NOT_FOUND;
    }
}
