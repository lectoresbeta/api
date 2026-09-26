<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Catalogue\Domain\Entity;

use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * Una corrección entregada sobre una obra, contada para el catálogo
 * (`decision:0008`).
 *
 * Alimenta el factor de **desatención**: cuantas más lleva recibidas una
 * obra, más abajo aparece. No es una métrica de éxito y no se enseña como
 * tal — cuenta para ordenar, y ordena hacia abajo.
 *
 * Se guarda **una fila por corrección** en vez de un contador, y no por
 * gusto: un contador tendría que defenderse de la reentrega del mismo hecho,
 * y una clave primaria lo hace sola. Contar es un `COUNT(*)` con un índice.
 *
 * De la corrección solo viaja su identificador. Ni el texto, ni quién la
 * escribió, ni lo que valió.
 */
class DeliveredCorrection
{
    private string $correctionId;

    private string $workId;

    private \DateTimeImmutable $submittedAt;

    public function __construct(string $correctionId, WorkId $workId, \DateTimeImmutable $submittedAt)
    {
        $this->correctionId = $correctionId;
        $this->workId = $workId->value();
        $this->submittedAt = $submittedAt;
    }

    public function correctionId(): string
    {
        return $this->correctionId;
    }

    public function workId(): WorkId
    {
        return WorkId::fromString($this->workId);
    }

    public function submittedAt(): \DateTimeImmutable
    {
        return $this->submittedAt;
    }
}
