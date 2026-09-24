<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\Repository;

use LectoresBeta\Work\Manuscript\Domain\Entity\Work;
use LectoresBeta\Work\Manuscript\Domain\Enum\WorkStatus;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

interface WorkRepository
{
    public function save(Work $work): void;

    public function ofId(WorkId $id): ?Work;

    /**
     * Several works in one query.
     *
     * It exists for `WorkAccessBriefs::ofWorks()`, which answers about a page
     * of requests at a time: one call per row would be an N+1 hidden behind a
     * contract.
     *
     * @param list<WorkId> $ids
     *
     * @return array<string, Work> keyed by identifier; missing ones are absent
     */
    public function ofIds(array $ids): array;

    /**
     * «Mis relatos» (`FEAT-WRK-015`). Filtering by status is optional
     * because the screen has tabs.
     *
     * @return list<Work>
     */
    public function ofAuthor(AuthorId $authorId, ?WorkStatus $status = null): array;

    /**
     * Cuántas obras tiene ese autor, **de cualquier estado**.
     *
     * Contar y no traerlas: es un número de la cabecera de un perfil, y
     * cargar las obras para contarlas sería pagar la lista entera por una
     * cifra (`FEAT-USR-028`).
     */
    public function countByAuthor(AuthorId $authorId): int;

    public function remove(Work $work): void;
}
