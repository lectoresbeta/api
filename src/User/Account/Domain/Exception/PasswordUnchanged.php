<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * La nueva contraseña es la que ya había (`FEAT-USR-041` `RN-6`).
 *
 * Se rechaza en vez de aceptarse en silencio porque el efecto secundario no
 * es inocuo: un cambio cierra todas las sesiones y manda un aviso de
 * seguridad. Quien pulsa «guardar» sin tocar el campo no espera ninguna de
 * las dos cosas.
 */
final class PasswordUnchanged extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('The new password is the same as the current one.');
    }

    public function errorCode(): string
    {
        return 'PASSWORD_UNCHANGED';
    }

    public function kind(): FailureKind
    {
        return FailureKind::INVALID;
    }
}
