<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Contract;

/**
 * Lo que hace falta saber de una corrección para decidir si se puede
 * reclamar (`FEAT-MOD-001` `RN-4`, `RN-5`).
 *
 * Tres datos y ninguno es su contenido: quién la recibió, quién la escribió y
 * si ya se puede leer. Quien pregunta está comprobando permisos, no leyendo
 * la corrección.
 */
final readonly class ClaimableCorrection
{
    public function __construct(
        public string $correctionId,
        public string $ownerId,
        public string $readerId,
        public bool $isReadable,
    ) {
    }
}
