<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Questionnaire\Domain\Entity;

use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;
use LectoresBeta\Work\Questionnaire\Domain\ValueObject\QuestionnaireId;

/**
 * The questions that steer the feedback for one work (`FEAT-WRK-014`).
 *
 * **Versioned, and old versions are kept** (`RN-4`). A correction always
 * answers the version it started with: whoever began working under certain
 * terms keeps them, even if the author raises the demands afterwards
 * (`decision:0006` `RN-7`). Overwriting would silently change what a reader
 * agreed to do and what they get paid for it.
 *
 * A new version is a new row, not an edit. `(work_id, version)` is unique.
 */
class Questionnaire
{
    private string $id;

    private string $workId;

    private int $version;

    private \DateTimeImmutable $createdAt;

    private \DateTimeImmutable $updatedAt;

    public function __construct(
        QuestionnaireId $id,
        WorkId $workId,
        int $version,
        \DateTimeImmutable $now,
    ) {
        $this->id = $id->value();
        $this->workId = $workId->value();
        $this->version = $version;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function id(): QuestionnaireId
    {
        return QuestionnaireId::fromString($this->id);
    }

    public function workId(): WorkId
    {
        return WorkId::fromString($this->workId);
    }

    public function version(): int
    {
        return $this->version;
    }

    public function touch(\DateTimeImmutable $now): void
    {
        $this->updatedAt = $now;
    }
}
