<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Relationship\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * No se puede bloquear a esa persona (`FEAT-COM-034`).
 *
 * Dos casos, y lo que **no** está aquí importa tanto como lo que está:
 * bloquear a quien ya tienes bloqueado no es un error, y desbloquear a quien
 * no lo está tampoco. En los dos el estado que se pedía ya se cumple.
 */
final class BlockRefused extends \DomainException implements BusinessFailure
{
    private function __construct(
        private readonly string $failureCode,
        private readonly FailureKind $failureKind,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function userNotFound(): self
    {
        return new self(
            'USER_NOT_FOUND',
            FailureKind::NOT_FOUND,
            'That account does not exist.',
        );
    }

    public static function yourself(): self
    {
        return new self(
            'CANNOT_BLOCK_YOURSELF',
            FailureKind::INVALID,
            'You cannot block yourself.',
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
