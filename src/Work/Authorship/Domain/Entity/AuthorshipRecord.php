<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Authorship\Domain\Entity;

use LectoresBeta\Work\Authorship\Domain\ValueObject\AuthorshipRecordId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * Evidence that a given text existed, in a given shape, on a given date
 * (`FEAT-WRK-009`).
 *
 * Insert only: editing a work produces a **new** record, never a change to
 * the previous one (`RN-5`). A record that could be rewritten would prove
 * nothing.
 *
 * What is stored is a cryptographic **fingerprint** of the content, not the
 * content and not an encrypted copy. The two get confused (`P-4`): a hash
 * proves that a text matches a date, which is what authorship needs, and it
 * cannot be turned back into the manuscript.
 */
class AuthorshipRecord
{
    private string $id;

    private string $workId;

    private string $authorId;

    /** SHA-256 over the plain text of every chapter, in order. */
    private string $contentHash;

    private string $algorithm;

    private int $wordCount;

    private int $chapterCount;

    private \DateTimeImmutable $recordedAt;

    public function __construct(
        AuthorshipRecordId $id,
        WorkId $workId,
        AuthorId $authorId,
        string $contentHash,
        int $wordCount,
        int $chapterCount,
        \DateTimeImmutable $now,
        string $algorithm = 'sha256',
    ) {
        $this->id = $id->value();
        $this->workId = $workId->value();
        $this->authorId = $authorId->value();
        $this->contentHash = $contentHash;
        $this->wordCount = $wordCount;
        $this->chapterCount = $chapterCount;
        $this->recordedAt = $now;
        $this->algorithm = $algorithm;
    }

    public function id(): AuthorshipRecordId
    {
        return AuthorshipRecordId::fromString($this->id);
    }

    public function workId(): WorkId
    {
        return WorkId::fromString($this->workId);
    }

    public function authorId(): AuthorId
    {
        return AuthorId::fromString($this->authorId);
    }

    public function contentHash(): string
    {
        return $this->contentHash;
    }

    public function recordedAt(): \DateTimeImmutable
    {
        return $this->recordedAt;
    }
}
