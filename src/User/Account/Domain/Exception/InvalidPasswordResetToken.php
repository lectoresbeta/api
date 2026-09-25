<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * Ese enlace no sirve (`FEAT-USR-007` `RN-12`).
 *
 * **Un token que no existe, uno ya usado y uno invalidado responden igual.**
 * Distinguirlos diría si ese valor llegó a existir alguna vez, que es
 * información sobre una cuenta ajena para quien esté probando.
 *
 * La caducidad sí se distingue, y solo ella: es la única que le dice a quien
 * está delante algo que puede usar —pedir otro— en vez de dejarle pensando
 * que copió mal el enlace.
 */
final class InvalidPasswordResetToken extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('The password reset link is not valid.');
    }

    public function errorCode(): string
    {
        return 'INVALID_PASSWORD_RESET_TOKEN';
    }

    public function kind(): FailureKind
    {
        return FailureKind::NOT_FOUND;
    }
}
