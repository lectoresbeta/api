<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * La corrección está retenida por descubierto
 * ([`FEAT-CRD-018`](../../../../../docs/features/credits/FEAT-CRD-018-negative-balance.md)).
 *
 * Se distingue del `404` a propósito: aquí **el autor sí debe saber que
 * existe**, porque existe, es suya y el camino para leerla es reponer saldo.
 * Un «no encontrado» le haría creer que nadie le ha corregido y le quitaría
 * la única razón para volver a la plataforma.
 */
final class CorrectionLocked extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('That correction cannot be read until the balance is back above zero.');
    }

    public function errorCode(): string
    {
        return 'CORRECTION_LOCKED';
    }

    public function kind(): FailureKind
    {
        return FailureKind::CONFLICT;
    }
}
