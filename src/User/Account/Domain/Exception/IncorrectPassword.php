<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * La contraseña actual no es la que se ha escrito (`FEAT-USR-041` `RN-1`).
 *
 * **Exigirla no es una formalidad**: una sesión abierta no basta para
 * cambiarla. Es lo único que impide que quien se siente delante de un
 * portátil desbloqueado se quede con la cuenta, porque cambiar la contraseña
 * echa a todos los demás.
 */
final class IncorrectPassword extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('The current password is not correct.');
    }

    public function errorCode(): string
    {
        return 'INCORRECT_PASSWORD';
    }

    public function kind(): FailureKind
    {
        return FailureKind::FORBIDDEN;
    }
}
