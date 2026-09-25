<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Review\Application\DTO;

/**
 * Una página de la cola, **con cuántas hay detrás** (`FEAT-MOD-008`).
 *
 * El total es lo que convierte una lista en una cola. Sin él, quien modera ve
 * veinte expedientes y no sabe si detrás hay cero o mil, que es justo la
 * información con la que se decide si hoy hay que pedir ayuda.
 *
 * `total` cuenta **lo mismo que la lista**, filtros incluidos. Un total que
 * contara otra cosa sería peor que no darlo, porque nadie lo comprueba.
 */
final readonly class ClaimQueue
{
    /**
     * @param list<ClaimInQueue> $claims
     */
    public function __construct(
        public array $claims,
        public int $total,
    ) {
    }
}
