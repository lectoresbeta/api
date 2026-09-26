<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureDetails;
use LectoresBeta\Shared\Domain\Exception\FailureKind;
use LectoresBeta\Shared\Domain\Exception\RetryAfter;

/**
 * Hay un intervalo mínimo entre peticiones del enlace (`FEAT-USR-007`
 * `RN-3`).
 *
 * Distinto del tope del periodo por lo mismo que en `FEAT-USR-021`: «espera
 * un minuto» y «vuelve mañana» no son el mismo mensaje para quien está
 * delante, y con un solo código la pantalla tendría que enseñar siempre el
 * pesimista.
 */
final class PasswordResetRequestedTooSoon extends \DomainException implements BusinessFailure, FailureDetails, RetryAfter
{
    private function __construct(private readonly int $seconds)
    {
        parent::__construct('Wait a moment before asking for the link again.');
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
        return 'PASSWORD_RESET_TOO_SOON';
    }

    public function kind(): FailureKind
    {
        return FailureKind::RATE_LIMITED;
    }
}
