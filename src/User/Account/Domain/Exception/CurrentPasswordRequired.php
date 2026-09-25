<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * El campo «contraseña actual» solo puede ir vacío si la cuenta no tiene
 * ninguna (`FEAT-USR-041` `RN-8`).
 *
 * El mismo formulario sirve para **cambiarla** y para **establecerla por
 * primera vez** —quien entró con Google no tiene ninguna que aportar—, y
 * esta comprobación es lo que impide usar el segundo camino para saltarse el
 * primero. **La hace el servidor**: un cliente que mande el campo vacío
 * contra una cuenta con contraseña recibe esto, no un cambio.
 *
 * Se distingue de «la contraseña actual es incorrecta» porque le dice a quien
 * llama algo distinto: no es que se haya equivocado, es que falta el campo.
 * No filtra nada — quien pregunta ya tiene la sesión de esa cuenta.
 */
final class CurrentPasswordRequired extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('This account already has a password, so the current one is required.');
    }

    public function errorCode(): string
    {
        return 'CURRENT_PASSWORD_REQUIRED';
    }

    public function kind(): FailureKind
    {
        return FailureKind::FORBIDDEN;
    }
}
