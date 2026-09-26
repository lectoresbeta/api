<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\Entity;

use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * Qué nota le puso una persona a una obra, según lo último que `Feedback`
 * dijo (`FEAT-WRK-015`).
 *
 * **No es la valoración**: la valoración vive en `Feedback`, que es quien la
 * recibe y quien decide si alguien puede dejarla. Esto es lo mínimo que
 * `Work` necesita recordar para mantener su agregado.
 *
 * Y lo necesita porque `WorkRated` **no lleva la nota anterior**. Al cambiar
 * una valoración hay que restar la que había, y sin esta fila la suma
 * contaría las dos. Guardar la última conocida por persona la recupera, y de
 * paso hace el consumo idempotente: reprocesar el mismo hecho escribe la
 * misma nota y no mueve nada.
 */
class WorkReaderRating
{
    private string $workId;

    private string $readerId;

    private int $value;

    private \DateTimeImmutable $ratedAt;

    public function __construct(WorkId $workId, string $readerId, int $value, \DateTimeImmutable $ratedAt)
    {
        $this->workId = $workId->value();
        $this->readerId = $readerId;
        $this->value = $value;
        $this->ratedAt = $ratedAt;
    }

    public function value(): int
    {
        return $this->value;
    }

    /**
     * Responde la nota que había, o `null` si no cambia nada: la cola
     * reentrega, y volver a aplicar lo mismo no es una valoración nueva.
     */
    public function changeTo(int $value, \DateTimeImmutable $ratedAt): ?int
    {
        if ($value === $this->value) {
            return null;
        }

        $previous = $this->value;
        $this->value = $value;
        $this->ratedAt = $ratedAt;

        return $previous;
    }
}
