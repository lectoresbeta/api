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
     * «Mis relatos» (`FEAT-WRK-015`). Filtering by status is optional
     * because the screen has tabs.
     *
     * @return list<Work>
     */
    public function ofAuthor(AuthorId $authorId, ?WorkStatus $status = null): array;

    public function remove(Work $work): void;
}
