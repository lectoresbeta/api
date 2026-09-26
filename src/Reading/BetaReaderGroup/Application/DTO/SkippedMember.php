<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderGroup\Application\DTO;

/**
 * Alguien del grupo al que **no** se ha invitado, y por qué
 * (`FEAT-RDG-007` `RN-12`).
 *
 * El motivo es el mismo código de error que habría devuelto la invitación
 * individual. Inventar un vocabulario distinto para el caso en bloque
 * obligaría al cliente a conocer dos.
 */
final readonly class SkippedMember
{
    public function __construct(
        public string $userId,
        public string $reason,
    ) {
    }
}
