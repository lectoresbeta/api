<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Ranking\Domain\Entity;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;

/**
 * Counters about one author, kept as a projection (`FEAT-COM-016`).
 *
 * Fed by integration events, never by querying `User`, `Work` or `Feedback`
 * (`RN-6`). It is what lets the home page be painted without a single
 * cross-context read.
 *
 * How each ranking is actually scored is still open (`CM-4`), which is why
 * this holds counts and no score.
 */
class AuthorStats
{
    private string $authorId;

    private ?string $displayName = null;

    private ?string $avatarUrl = null;

    private int $followers = 0;

    private int $posts = 0;

    private int $publishedWorks = 0;

    private int $correctionsDelivered = 0;

    private int $tipsReceived = 0;

    private \DateTimeImmutable $updatedAt;

    public function __construct(MemberId $authorId, \DateTimeImmutable $now)
    {
        $this->authorId = $authorId->value();
        $this->updatedAt = $now;
    }

    public function authorId(): MemberId
    {
        return MemberId::fromString($this->authorId);
    }

    public function followers(): int
    {
        return $this->followers;
    }

    public function describe(?string $displayName, ?string $avatarUrl, \DateTimeImmutable $now): void
    {
        $this->displayName = $displayName;
        $this->avatarUrl = $avatarUrl;
        $this->updatedAt = $now;
    }

    public function followerGained(\DateTimeImmutable $now): void
    {
        ++$this->followers;
        $this->updatedAt = $now;
    }

    public function followerLost(\DateTimeImmutable $now): void
    {
        $this->followers = max(0, $this->followers - 1);
        $this->updatedAt = $now;
    }

    public function postPublished(\DateTimeImmutable $now): void
    {
        ++$this->posts;
        $this->updatedAt = $now;
    }

    public function workPublished(\DateTimeImmutable $now): void
    {
        ++$this->publishedWorks;
        $this->updatedAt = $now;
    }

    public function correctionDelivered(\DateTimeImmutable $now): void
    {
        ++$this->correctionsDelivered;
        $this->updatedAt = $now;
    }

    public function tipReceived(int $amount, \DateTimeImmutable $now): void
    {
        $this->tipsReceived += max(0, $amount);
        $this->updatedAt = $now;
    }
}
