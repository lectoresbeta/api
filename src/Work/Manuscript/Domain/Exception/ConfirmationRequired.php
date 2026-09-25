<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * Falta la confirmación explícita (`FEAT-WRK-006` `RN-2`).
 *
 * La comprueba el servidor y no solo la pantalla: un `DELETE` que se dispara
 * por un enlace mal pulsado, o por un cliente que repite peticiones, no
 * debería vaciar el perfil de nadie.
 */
final class ConfirmationRequired extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('This operation must be confirmed explicitly.');
    }

    public function errorCode(): string
    {
        return 'CONFIRMATION_REQUIRED';
    }

    public function kind(): FailureKind
    {
        return FailureKind::INVALID;
    }
}
