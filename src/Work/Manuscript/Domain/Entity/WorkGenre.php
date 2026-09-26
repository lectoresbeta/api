<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\Entity;

use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * Una temática de una obra (`FEAT-WRK-001`).
 *
 * Una fila por obra y temática, y no una lista dentro del agregado: así el
 * catálogo puede filtrar con un índice en vez de leer y deserializar cada
 * obra, que es la única consulta que de verdad usa este dato.
 *
 * Guarda **el código y no el nombre**. El nombre lo cura `User` y puede
 * cambiar —«Drama» y «Teatro» ya se confundieron una vez—; el código es lo
 * que significa lo mismo en las dos pantallas.
 *
 * Una temática retirada del catálogo sigue apuntada aquí a propósito: retirar
 * no borra, y una obra clasificada no se desclasifica porque alguien curase
 * la lista.
 *
 * **`W-7` resuelta**: `Work` no tiene catálogo propio. Consume el de `User`
 * por su contrato publicado, que es lo que hace que «Ficción» signifique lo
 * mismo cuando una persona dice lo que le gusta y cuando un autor clasifica
 * su obra.
 */
class WorkGenre
{
    private string $workId;

    private string $genreCode;

    public function __construct(WorkId $workId, string $genreCode)
    {
        $this->workId = $workId->value();
        $this->genreCode = strtoupper($genreCode);
    }

    public function workId(): WorkId
    {
        return WorkId::fromString($this->workId);
    }

    public function genreCode(): string
    {
        return $this->genreCode;
    }
}
