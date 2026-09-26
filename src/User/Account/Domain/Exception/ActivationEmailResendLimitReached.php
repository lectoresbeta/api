<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureDetails;
use LectoresBeta\Shared\Domain\Exception\FailureKind;
use LectoresBeta\Shared\Domain\Exception\RetryAfter;

/**
 * Se ha agotado el máximo de reenvíos del periodo (`FEAT-USR-021` `RN-3`).
 *
 * El intervalo mínimo por sí solo no basta: espaciando las peticiones un
 * minuto se pueden mandar mil cuatrocientos correos al día a una dirección
 * ajena, con el dominio de la plataforma en el remitente. **El tope es lo que
 * impide usar este formulario como amplificador de spam.**
 *
 * Quien lo agota de buena fe tiene un problema que un reenvío más no arregla:
 * la dirección está mal escrita, o el correo no llega. La salida es soporte,
 * no insistir.
 */
final class ActivationEmailResendLimitReached extends \DomainException implements BusinessFailure, FailureDetails, RetryAfter
{
    private function __construct(private readonly int $seconds)
    {
        parent::__construct('Too many activation emails have been requested for this address.');
    }

    public static function inSeconds(int $seconds): self
    {
        return new self(max(0, $seconds));
    }

    public function retryAfterSeconds(): int
    {
        return $this->seconds;
    }

    public function failureDetails(): array
    {
        return ['retryAfterSeconds' => $this->seconds];
    }

    public function errorCode(): string
    {
        return 'RESEND_LIMIT_REACHED';
    }

    public function kind(): FailureKind
    {
        return FailureKind::RATE_LIMITED;
    }
}
