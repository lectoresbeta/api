<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Domain\Entity;

use LectoresBeta\Work\Chapter\Domain\Enum\ChapterVisibility;
use LectoresBeta\Work\Chapter\Domain\Service\WordCounter;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * A fragment of a work, and **the unit that gets corrected**
 * (`FEAT-FBK-003`).
 *
 * That is why the word count lives here and not only on the work: the price
 * of a correction is per chapter.
 *
 * The content is a column and not external storage. A chapter is at most a
 * few tens of thousands of words, it is read whole, and keeping it in
 * PostgreSQL means the text is covered by the same backup and the same
 * transaction as everything else. `P-2` can still move it later; nothing in
 * the domain model would change.
 */
class Chapter
{
    private string $id;

    private string $workId;

    private int $position;

    private ?string $title = null;

    private string $content = '';

    private int $wordCount = 0;

    private ChapterVisibility $visibility;

    /** Blocked by an upheld claim (`FEAT-MOD-003`, `MOD-13`). */
    private ?\DateTimeImmutable $blockedAt = null;

    private \DateTimeImmutable $createdAt;

    private \DateTimeImmutable $updatedAt;

    public function __construct(
        ChapterId $id,
        WorkId $workId,
        int $position,
        \DateTimeImmutable $now,
        ?string $title = null,
    ) {
        $this->id = $id->value();
        $this->workId = $workId->value();
        $this->position = $position;
        $this->title = $title;
        $this->visibility = ChapterVisibility::VISIBLE;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function id(): ChapterId
    {
        return ChapterId::fromString($this->id);
    }

    public function workId(): WorkId
    {
        return WorkId::fromString($this->workId);
    }

    public function position(): int
    {
        return $this->position;
    }

    public function title(): ?string
    {
        return $this->title;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function wordCount(): int
    {
        return $this->wordCount;
    }

    public function visibility(): ChapterVisibility
    {
        return $this->visibility;
    }

    public function isBlocked(): bool
    {
        return null !== $this->blockedAt;
    }

    /**
     * The word count is recomputed here and never passed in: it is derived
     * from the content, and the whole price depends on it.
     */
    public function replaceContent(string $content, WordCounter $counter, \DateTimeImmutable $now): void
    {
        $this->content = $content;
        $this->wordCount = $counter->count($content);
        $this->updatedAt = $now;
    }

    public function retitle(?string $title, \DateTimeImmutable $now): void
    {
        $this->title = null === $title ? null : trim($title);
        $this->updatedAt = $now;
    }

    public function moveTo(int $position, \DateTimeImmutable $now): void
    {
        $this->position = $position;
        $this->updatedAt = $now;
    }

    public function hide(\DateTimeImmutable $now): void
    {
        $this->visibility = ChapterVisibility::HIDDEN;
        $this->updatedAt = $now;
    }

    public function show(\DateTimeImmutable $now): void
    {
        $this->visibility = ChapterVisibility::VISIBLE;
        $this->updatedAt = $now;
    }

    public function block(\DateTimeImmutable $now): void
    {
        $this->blockedAt ??= $now;
        $this->updatedAt = $now;
    }

    public function unblock(\DateTimeImmutable $now): void
    {
        $this->blockedAt = null;
        $this->updatedAt = $now;
    }
}
