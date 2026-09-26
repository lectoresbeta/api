<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Review\Application\Query;

/**
 * Qué cola se pide (`FEAT-MOD-002`, `FEAT-MOD-008`).
 *
 * **No hay parámetro de ordenación**, y esa ausencia es una regla: la cola va
 * siempre de lo más antiguo a lo más reciente. Dejar elegir el orden a quien
 * la trabaja hace que los casos incómodos se hundan, y una reclamación sin
 * resolver es alguien esperando.
 */
final readonly class ListClaims
{
    public function __construct(
        public string $moderatorId,
        public int $limit = 50,
        public int $offset = 0,
        /**
         * El motivo por el que se acota. Es el filtro que pidió la ficha: son
         * doce motivos y no piden ni el mismo criterio ni, a veces, la misma
         * persona.
         */
        public ?string $reason = null,
        /**
         * Y sobre qué clase de cosa. Revisar textos y revisar conducta son
         * dos trabajos distintos.
         */
        public ?string $targetType = null,
    ) {
    }
}
