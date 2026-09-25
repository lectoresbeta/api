<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\Repository;

use LectoresBeta\Work\Manuscript\Domain\Enum\ContentWarning;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

interface WorkContentWarningRepository
{
    /**
     * Sustituye las etiquetas de la obra por estas. **No hay «añadir una»**:
     * lo que el autor declara es qué contiene su obra, entera, y la
     * operación natural es dejar la lista como la ve en pantalla.
     *
     * @param list<ContentWarning> $warnings
     */
    public function replaceAll(WorkId $workId, array $warnings): void;

    /**
     * @return list<ContentWarning>
     */
    public function of(WorkId $workId): array;

    /**
     * Las de varias obras de una vez, para pintar una página sin una consulta
     * por tarjeta (`FEAT-COM-028`).
     *
     * @param list<WorkId> $workIds
     *
     * @return array<string, list<ContentWarning>> indexado por identificador de obra
     */
    public function ofWorks(array $workIds): array;
}
