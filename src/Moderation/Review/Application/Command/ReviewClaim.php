<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Review\Application\Command;

/**
 * Tomar una reclamación y resolverla, en un solo paso (`FEAT-MOD-002`).
 *
 * No hay un «asignármela» separado a propósito: una asignación que se puede
 * pedir y no usar es una cola con expedientes retenidos por moderadores que
 * ya no están mirando. Lo que `RN-8` necesita —que dos no resuelvan la misma—
 * lo da el bloqueo de la fila durante la decisión, no un estado intermedio.
 */
final readonly class ReviewClaim
{
    public function __construct(
        public string $claimId,
        public string $moderatorId,
        public string $decision,
        public string $motivation,
    ) {
    }
}
