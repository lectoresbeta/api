<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Review\Application\DTO;

/**
 * Lo que devuelve resolver una reclamación.
 *
 * Confirma la decisión y nada más: los efectos los aplica cada contexto
 * cuando reciba el hecho (`RN-4`), así que aquí no hay nada que contar sobre
 * créditos ni sobre obras bloqueadas. Decirlo antes de que ocurra sería
 * prometer por otros.
 */
final readonly class ReviewedClaim
{
    public function __construct(
        public string $claimId,
        public string $status,
        public string $decision,
    ) {
    }
}
