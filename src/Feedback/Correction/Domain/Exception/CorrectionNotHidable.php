<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * Esa corrección no se puede apartar (`FEAT-FBK-007` `RN-4`).
 *
 * Solo pasa con una **retenida por descubierto**: apartar es una decisión
 * sobre algo que se ha leído, y de una `LOCKED` no se ha leído nada. Se dice
 * por qué, y con la salida — reponer saldo la libera y entonces sí se puede
 * apartar.
 */
final class CorrectionNotHidable extends \DomainException implements BusinessFailure
{
    public static function becauseItIsLocked(): self
    {
        return new self('A withheld correction cannot be set aside before it has been read.');
    }

    public function errorCode(): string
    {
        return 'CORRECTION_IS_LOCKED';
    }

    public function kind(): FailureKind
    {
        return FailureKind::CONFLICT;
    }
}
