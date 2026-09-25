<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * Esa dirección no se puede usar (`FEAT-USR-040` `RN-4`).
 *
 * **Un solo código para dos casos a propósito**: que ya la tenga otra cuenta
 * y que sea la que ya tienes. Decir «ese correo ya está registrado»
 * convertiría este formulario en un comprobador de quién tiene cuenta en la
 * plataforma, que es el mismo problema que en el registro y merece el mismo
 * trato.
 *
 * Lo que sí se distingue es el formato, porque eso el cliente ya puede
 * comprobarlo y no dice nada de nadie.
 */
final class EmailChangeRefused extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('That address cannot be used for this account.');
    }

    public function errorCode(): string
    {
        return 'EMAIL_CHANGE_REFUSED';
    }

    public function kind(): FailureKind
    {
        return FailureKind::INVALID;
    }
}
