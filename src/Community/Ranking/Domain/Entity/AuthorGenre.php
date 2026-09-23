<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Ranking\Domain\Entity;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;

/**
 * Which genres an author writes in, projected from `WorkPublished`
 * (`FEAT-COM-016`).
 *
 * It is what lets «authors you might like» be answered by genre without
 * reading `Work`'s tables.
 */
class AuthorGenre
{
    private string $authorId;

    private string $genreCode;

    private int $works = 0;

    public function __construct(MemberId $authorId, string $genreCode)
    {
        $this->authorId = $authorId->value();
        $this->genreCode = strtoupper($genreCode);
    }

    public function authorId(): MemberId
    {
        return MemberId::fromString($this->authorId);
    }

    public function genreCode(): string
    {
        return $this->genreCode;
    }

    public function workCounted(): void
    {
        ++$this->works;
    }
}
