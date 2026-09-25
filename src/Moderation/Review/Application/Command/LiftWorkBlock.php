<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Review\Application\Command;

/**
 * Levantar el bloqueo de una obra o de un capítulo (`FEAT-MOD-003` `RN-7`).
 *
 * Lleva motivación por lo mismo que la decisión que lo impuso: un expediente
 * sin motivo no se puede auditar ni defender, y **deshacer** una decisión
 * necesita explicarse todavía más que tomarla.
 */
final readonly class LiftWorkBlock
{
    public function __construct(
        public string $targetType,
        public string $targetId,
        public string $moderatorId,
        public string $motivation,
    ) {
    }
}
