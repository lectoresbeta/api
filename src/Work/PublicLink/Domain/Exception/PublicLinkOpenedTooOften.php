<?php

declare(strict_types=1);

namespace LectoresBeta\Work\PublicLink\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureDetails;
use LectoresBeta\Shared\Domain\Exception\FailureKind;
use LectoresBeta\Shared\Domain\Exception\RetryAfter;

/**
 * Demasiadas aperturas desde el mismo origen (`FEAT-WRK-010` `RN-16`).
 *
 * Un token opaco de 256 bits no se adivina, pero un formulario público sin
 * límite invita a intentarlo, y cada intento es una consulta a la base de
 * datos que alguien paga. El límite es la diferencia entre «imposible» e
 * «imposible y además caro para quien lo intenta».
 */
final class PublicLinkOpenedTooOften extends \DomainException implements BusinessFailure, FailureDetails, RetryAfter
{
    private function __construct(private readonly int $seconds)
    {
        parent::__construct('Too many attempts from here. Try again later.');
    }

    public static function inSeconds(int $seconds): self
    {
        return new self(max(0, $seconds));
    }

    public function failureDetails(): array
    {
        return ['retryAfterSeconds' => $this->seconds];
    }

    public function retryAfterSeconds(): int
    {
        return $this->seconds;
    }

    public function errorCode(): string
    {
        return 'TOO_MANY_ATTEMPTS';
    }

    public function kind(): FailureKind
    {
        return FailureKind::RATE_LIMITED;
    }
}
