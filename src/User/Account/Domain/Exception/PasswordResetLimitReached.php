<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureDetails;
use LectoresBeta\Shared\Domain\Exception\FailureKind;
use LectoresBeta\Shared\Domain\Exception\RetryAfter;

/**
 * Se ha agotado el máximo de enlaces del periodo (`FEAT-USR-007` `RN-3`).
 *
 * El intervalo mínimo por sí solo no basta: espaciando las peticiones se
 * pueden mandar cientos de correos «restablece tu contraseña» a una dirección
 * ajena, con el dominio de la plataforma en el remitente. Además de ser spam,
 * **es la forma en que se prepara un engaño**: quien recibe veinte avisos
 * seguidos acaba pulsando el enlace del veintiuno, que puede no ser el
 * nuestro.
 */
final class PasswordResetLimitReached extends \DomainException implements BusinessFailure, FailureDetails, RetryAfter
{
    private function __construct(private readonly int $seconds)
    {
        parent::__construct('Too many password reset links have been requested for this address.');
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
        return 'PASSWORD_RESET_LIMIT_REACHED';
    }

    public function kind(): FailureKind
    {
        return FailureKind::RATE_LIMITED;
    }
}
