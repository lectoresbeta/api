<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Catalog\Domain\Entity;

use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * One correctable chapter, ready to be listed (`decision:0008`, `L-9`).
 *
 * A read model, fed by events from three contexts: `CreditBalanceChanged` and
 * `ChapterCorrectabilityChanged` from `Credits`, `WorkOpenedForCorrection`
 * and `WorkContentUpdated` from `Work` itself, `FeedbackSubmitted` from
 * `Feedback`. It exists because ordering needs the author's balance and the
 * chapter's price, both of which belong to `Credits`, and a `JOIN` across
 * contexts is exactly what `decision:0002` forbids.
 *
 * It holds what is needed to filter and to sort, and **no content**. It can
 * be rebuilt from scratch by replaying the events, so it is never the source
 * of truth for anything.
 *
 * Being a few seconds stale is fine: the worst case is offering a chapter
 * that has just stopped being correctable, and the reader gets the same
 * message they would have got by arriving late.
 */
class CatalogueEntry
{
    private string $chapterId;

    private string $workId;

    private string $authorId;

    private string $workTitle;

    private ?string $chapterTitle = null;

    private int $chapterPosition;

    private int $wordCount = 0;

    private int $price = 0;

    private int $authorBalance = 0;

    private int $correctionsReceived = 0;

    private int $openCorrections = 0;

    /**
     * Whether `Credits` says this chapter can be paid for right now. A plain
     * boolean and never an amount: the catalogue must not know anybody's
     * balance in detail (`ChapterCorrectabilityChanged`).
     */
    private bool $correctable = false;

    private bool $adultsOnly = false;

    /** @var list<string> */
    private array $genres = [];

    /** @var list<string> */
    private array $contentWarnings = [];

    private \DateTimeImmutable $openedAt;

    private \DateTimeImmutable $updatedAt;

    public function __construct(
        ChapterId $chapterId,
        WorkId $workId,
        AuthorId $authorId,
        string $workTitle,
        int $chapterPosition,
        \DateTimeImmutable $openedAt,
    ) {
        $this->chapterId = $chapterId->value();
        $this->workId = $workId->value();
        $this->authorId = $authorId->value();
        $this->workTitle = $workTitle;
        $this->chapterPosition = $chapterPosition;
        $this->openedAt = $openedAt;
        $this->updatedAt = $openedAt;
    }

    public function chapterId(): ChapterId
    {
        return ChapterId::fromString($this->chapterId);
    }

    public function workId(): WorkId
    {
        return WorkId::fromString($this->workId);
    }

    public function authorId(): AuthorId
    {
        return AuthorId::fromString($this->authorId);
    }

    public function price(): int
    {
        return $this->price;
    }

    public function authorBalance(): int
    {
        return $this->authorBalance;
    }

    public function correctionsReceived(): int
    {
        return $this->correctionsReceived;
    }

    public function openCorrections(): int
    {
        return $this->openCorrections;
    }

    public function isCorrectable(): bool
    {
        return $this->correctable;
    }

    public function weeksOpenAt(\DateTimeImmutable $moment): float
    {
        return max(0, $moment->getTimestamp() - $this->openedAt->getTimestamp()) / 604800;
    }

    /**
     * @param list<string> $genres
     * @param list<string> $contentWarnings
     */
    public function describeWork(
        string $workTitle,
        ?string $chapterTitle,
        int $wordCount,
        array $genres,
        array $contentWarnings,
        bool $adultsOnly,
        \DateTimeImmutable $now,
    ): void {
        $this->workTitle = $workTitle;
        $this->chapterTitle = $chapterTitle;
        $this->wordCount = $wordCount;
        $this->genres = array_values($genres);
        $this->contentWarnings = array_values($contentWarnings);
        $this->adultsOnly = $adultsOnly;
        $this->updatedAt = $now;
    }

    public function updatePricing(int $price, int $authorBalance, bool $correctable, \DateTimeImmutable $now): void
    {
        $this->price = $price;
        $this->authorBalance = $authorBalance;
        $this->correctable = $correctable;
        $this->updatedAt = $now;
    }

    public function correctionStarted(\DateTimeImmutable $now): void
    {
        ++$this->openCorrections;
        $this->updatedAt = $now;
    }

    public function correctionFinished(\DateTimeImmutable $now): void
    {
        $this->openCorrections = max(0, $this->openCorrections - 1);
        ++$this->correctionsReceived;
        $this->updatedAt = $now;
    }

    public function correctionAbandoned(\DateTimeImmutable $now): void
    {
        $this->openCorrections = max(0, $this->openCorrections - 1);
        $this->updatedAt = $now;
    }
}
