<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * El enlace ha caducado (`FEAT-USR-007` `RN-6`).
 *
 * Vive una hora, mucho menos que el de activación. No es una asimetría
 * descuidada: un enlace de activación abre una cuenta recién creada y vacía,
 * y este **se queda con una que ya tiene historial, créditos y obra inédita**.
 *
 * Se distingue de un token inválido porque es lo único que quien está delante
 * puede resolver: pedir otro.
 */
final class PasswordResetTokenExpired extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('The password reset link has expired. Ask for a new one.');
    }

    public function errorCode(): string
    {
        return 'PASSWORD_RESET_TOKEN_EXPIRED';
    }

    public function kind(): FailureKind
    {
        return FailureKind::GONE;
    }
}
