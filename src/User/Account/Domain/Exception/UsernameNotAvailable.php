<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * Ese nombre no se puede tener (`FEAT-USR-034` `RN-2`, `RN-3`).
 *
 * **Un nombre en uso y uno retenido por un alias vigente responden lo mismo, a
 * propósito.** Distinguirlos contaría que alguien tuvo ese nombre y lo cambió
 * hace menos de un mes, que es información sobre una persona concreta y no
 * sobre la disponibilidad de una palabra.
 *
 * Un nombre reservado sí se distingue: ahí no hay nadie a quien proteger, y
 * quien lo pide merece saber que no es cuestión de esperar.
 */
final class UsernameNotAvailable extends \DomainException implements BusinessFailure
{
    private function __construct(
        private readonly string $failureCode,
        private readonly FailureKind $failureKind,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function taken(): self
    {
        return new self(
            'USERNAME_TAKEN',
            FailureKind::CONFLICT,
            'That username is not available.',
        );
    }

    public static function reserved(): self
    {
        return new self(
            'USERNAME_RESERVED',
            FailureKind::INVALID,
            'That username is reserved.',
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
