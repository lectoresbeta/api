<?php

declare(strict_types=1);

namespace LectoresBeta\User\Authentication\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * Entrar con un proveedor externo no ha salido (`FEAT-USR-002`).
 *
 * Los casos se distinguen porque llevan a cosas distintas —volver a
 * intentarlo, aceptar los documentos, entrar con la contraseña de siempre— y
 * ninguno revela nada que no sepa ya quien acaba de autenticarse con Google.
 *
 * `providerUnavailable()` es la excepción a eso: responde `502` con un
 * mensaje genérico **a propósito**. Lo que falle al otro lado es asunto del
 * otro lado, y contarlo aquí sería exponer detalle técnico de una integración
 * a cualquiera que pulse un botón.
 */
final class ExternalSignInFailed extends \DomainException implements BusinessFailure
{
    private function __construct(
        private readonly string $failureCode,
        private readonly FailureKind $failureKind,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function unknownProvider(): self
    {
        return new self(
            'UNKNOWN_AUTH_PROVIDER',
            FailureKind::NOT_FOUND,
            'That sign-in provider is not available.',
        );
    }

    public static function providerUnavailable(): self
    {
        return new self(
            'AUTH_PROVIDER_UNAVAILABLE',
            FailureKind::UPSTREAM_FAILED,
            'The sign-in provider could not be reached. Try again in a moment.',
        );
    }

    /**
     * Sin correo no hay cuenta: es la identidad de la cuenta en esta
     * plataforma, y todo lo que se le manda a alguien va ahí.
     */
    public static function withoutEmail(): self
    {
        return new self(
            'EMAIL_NOT_SHARED',
            FailureKind::INVALID,
            'The provider did not share an email address.',
        );
    }

    /**
     * El correo ya tiene cuenta y el proveedor **no afirma** que esté
     * verificado (`RN-6`).
     *
     * Enlazar ahí sería entregar una cuenta ajena a quien supiera el correo
     * de su dueño. La salida es la de siempre: entrar con la contraseña.
     */
    public static function emailBelongsToAnotherAccount(): self
    {
        return new self(
            'EMAIL_ALREADY_REGISTERED',
            FailureKind::CONFLICT,
            'That email already has an account. Sign in with your password.',
        );
    }

    public function errorCode(): string
    {
        return $this->failureCode;
    }

    public function kind(): FailureKind
    {
        return $this->failureKind;
    }
}
