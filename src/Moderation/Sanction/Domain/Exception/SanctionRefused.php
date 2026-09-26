<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Sanction\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * No se puede imponer o levantar esa sanción (`FEAT-MOD-006`).
 */
final class SanctionRefused extends \DomainException implements BusinessFailure
{
    private function __construct(
        private readonly string $failureCode,
        private readonly FailureKind $failureKind,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function unknownType(): self
    {
        return new self('UNKNOWN_SANCTION_TYPE', FailureKind::INVALID, 'That is not a kind of sanction.');
    }

    /**
     * Una suspensión parcial **siempre lleva plazo** (`RN-2`): sin él sería
     * una total con otro nombre, y la total es una decisión distinta que se
     * toma a conciencia.
     */
    public static function durationMissing(): self
    {
        return new self('SANCTION_DURATION_REQUIRED', FailureKind::INVALID, 'A partial suspension always has a duration.');
    }

    public static function unknownDuration(): self
    {
        return new self('UNKNOWN_SANCTION_DURATION', FailureKind::INVALID, 'That is not a duration.');
    }

    /**
     * **El motivo es obligatorio** (`RN-1`, `RN-4`). Al usuario se le
     * comunica, y una sanción que no se entiende no corrige nada: solo hace
     * que la persona se vaya.
     */
    public static function reasonMissing(): self
    {
        return new self('SANCTION_REASON_REQUIRED', FailureKind::INVALID, 'A sanction always states its reason.');
    }

    public static function userNotFound(): self
    {
        return new self('USER_NOT_FOUND', FailureKind::NOT_FOUND, 'That account does not exist.');
    }

    public static function notFound(): self
    {
        return new self('SANCTION_NOT_FOUND', FailureKind::NOT_FOUND, 'That sanction does not exist.');
    }

    /**
     * Levantar lo ya levantado o lo ya caducado **sí es un error aquí**, al
     * revés que en otras operaciones idempotentes del proyecto: levantar una
     * sanción es un acto administrativo que se registra y se comunica, y
     * registrar dos veces el mismo dejaría un historial que no ocurrió.
     */
    public static function notInForce(): self
    {
        return new self('SANCTION_NOT_IN_FORCE', FailureKind::CONFLICT, 'That sanction is not in force.');
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
