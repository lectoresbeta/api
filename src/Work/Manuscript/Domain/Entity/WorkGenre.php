<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\Entity;

use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * One genre a work belongs to.
 *
 * The code is stored as an opaque string. The catalogue of record lives in
 * `User` (`FEAT-USR-023`) and this context cannot read that table, so `W-7`
 * — whether `Work` gets its own catalogue or consumes that one — is still
 * open. Until it closes, nothing here validates the code.
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
