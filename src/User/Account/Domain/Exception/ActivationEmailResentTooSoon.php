<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureDetails;
use LectoresBeta\Shared\Domain\Exception\FailureKind;
use LectoresBeta\Shared\Domain\Exception\RetryAfter;

/**
 * Hay un intervalo mínimo entre reenvíos (`FEAT-USR-021` `RN-2`).
 *
 * **Se distingue del límite diario a propósito.** Los dos son `429`, pero
 * significan cosas opuestas para quien está delante: este dice «espera un
 * minuto» y el otro «vuelve mañana». Un solo código obligaría a la pantalla a
 * enseñar el mensaje pesimista siempre.
 *
 * Que sea distinguible **no filtra nada**: el contador se consume en cada
 * petición, exista o no esa cuenta, así que un correo sin registrar responde
 * igual que uno registrado.
 */
final class ActivationEmailResentTooSoon extends \DomainException implements BusinessFailure, FailureDetails, RetryAfter
{
    private function __construct(private readonly int $seconds)
    {
        parent::__construct('Wait a moment before asking for the email again.');
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
        return 'RESEND_TOO_SOON';
    }

    public function kind(): FailureKind
    {
        return FailureKind::RATE_LIMITED;
    }
}
