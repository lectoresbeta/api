<?php

declare(strict_types=1);

namespace LectoresBeta\User\Legal\Domain\Entity;

use LectoresBeta\User\Legal\Domain\Enum\LegalDocumentType;
use LectoresBeta\User\Legal\Domain\ValueObject\LegalDocumentId;

/**
 * A version of a legal document (`FEAT-USR-024`).
 *
 * Versions are rows, not edits. What somebody accepted has to remain
 * readable years later, and that is impossible if publishing a new wording
 * overwrites the old one.
 */
class LegalDocument
{
    private string $id;

    private LegalDocumentType $type;

    private string $version;

    private \DateTimeImmutable $effectiveFrom;

    private string $url;

    public function __construct(
        LegalDocumentId $id,
        LegalDocumentType $type,
        string $version,
        \DateTimeImmutable $effectiveFrom,
        string $url,
    ) {
        $this->id = $id->value();
        $this->type = $type;
        $this->version = $version;
        $this->effectiveFrom = $effectiveFrom;
        $this->url = $url;
    }

    public function id(): LegalDocumentId
    {
        return LegalDocumentId::fromString($this->id);
    }

    public function type(): LegalDocumentType
    {
        return $this->type;
    }

    public function version(): string
    {
        return $this->version;
    }

    public function effectiveFrom(): \DateTimeImmutable
    {
        return $this->effectiveFrom;
    }

    public function url(): string
    {
        return $this->url;
    }

    public function isInForceAt(\DateTimeImmutable $moment): bool
    {
        return $this->effectiveFrom <= $moment;
    }
}
