<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Overdraft\Domain\Repository;

use LectoresBeta\Credits\Overdraft\Domain\ValueObject\ReactivationCandidate;

/**
 * Quién merecería un descubierto (`FEAT-CRD-019`).
 *
 * **Todo lo que hace falta para responder está dentro de `Credits`**, y eso
 * no es casualidad: el saldo, el precio de cada capítulo y el historial de
 * movimientos ya viven aquí. Preguntarle a otro contexto cuándo participó
 * alguien por última vez sería abrir una puerta para algo que este contexto
 * ya sabe.
 *
 * Lo que sí queda fuera es el tercer criterio de orden de la ficha —el
 * interés que despierta la obra, que se mide en lecturas y seguidores—:
 * vive en `Community` y traerlo aquí costaría una dependencia entre
 * contextos que este mecanismo no justifica.
 */
interface ReactivationCandidateRepository
{
    /**
     * Los mejores candidatos, ya ordenados y como mucho `$limit`.
     *
     * El orden es el de la ficha, con lo que este contexto puede ver: primero
     * quien **más ha corregido** —más capacidad de devolver la deuda—, y
     * entre iguales quien **lleva menos tiempo fuera**, que es quien más
     * probable es que vuelva.
     *
     * @param int<1, 100> $limit
     *
     * @return list<ReactivationCandidate>
     */
    public function best(\DateTimeImmutable $idleSince, \DateTimeImmutable $idleUntil, int $limit): array;
}
