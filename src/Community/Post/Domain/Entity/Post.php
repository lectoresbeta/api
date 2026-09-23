<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Domain\Entity;

use LectoresBeta\Community\Post\Domain\Enum\PostAudience;
use LectoresBeta\Community\Post\Domain\Enum\PostFormat;
use LectoresBeta\Community\Post\Domain\Enum\PostType;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Post\Domain\ValueObject\PostId;
use LectoresBeta\Community\Post\Domain\ValueObject\WorkId;

/**
 * A post on the wall (`FEAT-COM-002`).
 *
 * Type, format and audience are three separate columns because they are three
 * separate questions (`RN-5`).
 *
 * Counters live here rather than being counted on read. A wall that runs
 * `COUNT(*)` over comments and likes for every card degrades exactly when the
 * platform starts working.
 */
class Post
{
    private string $id;

    private string $authorId;

    private string $body;

    private PostType $type;

    private PostFormat $format;

    private PostAudience $audience;

    /** Set when the post promotes a work. The work itself belongs to `Work`. */
    private ?string $workId = null;

    private int $commentCount = 0;

    private int $likeCount = 0;

    private int $repostCount = 0;

    private \DateTimeImmutable $createdAt;

    private \DateTimeImmutable $updatedAt;

    private ?\DateTimeImmutable $deletedAt = null;

    public function __construct(
        PostId $id,
        MemberId $authorId,
        string $body,
        PostType $type,
        PostFormat $format,
        PostAudience $audience,
        \DateTimeImmutable $now,
    ) {
        $this->id = $id->value();
        $this->authorId = $authorId->value();
        $this->body = trim($body);
        $this->type = $type;
        $this->format = $format;
        $this->audience = $audience;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function id(): PostId
    {
        return PostId::fromString($this->id);
    }

    public function authorId(): MemberId
    {
        return MemberId::fromString($this->authorId);
    }

    public function body(): string
    {
        return $this->body;
    }

    public function type(): PostType
    {
        return $this->type;
    }

    public function format(): PostFormat
    {
        return $this->format;
    }

    public function audience(): PostAudience
    {
        return $this->audience;
    }

    public function workId(): ?WorkId
    {
        return null === $this->workId ? null : WorkId::fromString($this->workId);
    }

    public function isDeleted(): bool
    {
        return null !== $this->deletedAt;
    }

    public function promoteWork(WorkId $workId): void
    {
        $this->workId = $workId->value();
    }

    public function edit(string $body, \DateTimeImmutable $now): void
    {
        $this->body = trim($body);
        $this->updatedAt = $now;
    }

    public function delete(\DateTimeImmutable $now): void
    {
        $this->deletedAt ??= $now;
        $this->updatedAt = $now;
    }

    public function commentAdded(): void
    {
        ++$this->commentCount;
    }

    public function commentRemoved(): void
    {
        $this->commentCount = max(0, $this->commentCount - 1);
    }

    public function liked(): void
    {
        ++$this->likeCount;
    }

    public function unliked(): void
    {
        $this->likeCount = max(0, $this->likeCount - 1);
    }

    public function reposted(): void
    {
        ++$this->repostCount;
    }
}
