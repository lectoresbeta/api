<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\Entity;

use LectoresBeta\Work\Manuscript\Domain\Enum\ContentWarning;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * One label the author declared for a work (`FEAT-WRK-017`).
 *
 * A row per label rather than a column per label: the reader's filter asks
 * «which works carry any of these», which is an indexed lookup here and a
 * five-way OR the other way round.
 */
class WorkContentWarning
{
    private string $workId;

    /**
     * Held as the backed value and handed out as the enum.
     *
     * It is part of the primary key, and Doctrine's XML mapping does not
     * allow an enum on an identifier — the XSD rejects `enum-type` there.
     * Same trade-off the identifiers make, for the same reason.
     */
    private string $warning;

    public function __construct(WorkId $workId, ContentWarning $warning)
    {
        $this->workId = $workId->value();
        $this->warning = $warning->value;
    }

    public function workId(): WorkId
    {
        return WorkId::fromString($this->workId);
    }

    public function warning(): ContentWarning
    {
        return ContentWarning::from($this->warning);
    }
}
