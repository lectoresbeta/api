<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Domain\Entity;

use LectoresBeta\Community\Interaction\Domain\ValueObject\PostCommentId;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Post\Domain\ValueObject\PostId;

/**
 * A comment on a post, or a reply to one (`FEAT-COM-006`).
 *
 * `parentCommentId` is what makes replies possible without a second table
 * (`FEAT-COM-031`). Threads are **flattened to one level**: a reply to a
 * reply hangs from the root comment, never from the reply. Deep threads look
 * cheap and turn every read into a recursive query.
 */
class PostComment
{
    private string $id;

    private string $postId;

    private string $authorId;

    private ?string $parentCommentId = null;

    private string $body;

    private int $replyCount = 0;

    private \DateTimeImmutable $createdAt;

    private ?\DateTimeImmutable $editedAt = null;

    private ?\DateTimeImmutable $deletedAt = null;

    public function __construct(
        PostCommentId $id,
        PostId $postId,
        MemberId $authorId,
        string $body,
        \DateTimeImmutable $now,
        ?PostCommentId $parentCommentId = null,
    ) {
        $this->id = $id->value();
        $this->postId = $postId->value();
        $this->authorId = $authorId->value();
        $this->body = trim($body);
        $this->createdAt = $now;
        $this->parentCommentId = $parentCommentId?->value();
    }

    public function id(): PostCommentId
    {
        return PostCommentId::fromString($this->id);
    }

    public function postId(): PostId
    {
        return PostId::fromString($this->postId);
    }

    public function authorId(): MemberId
    {
        return MemberId::fromString($this->authorId);
    }

    public function parentCommentId(): ?PostCommentId
    {
        return null === $this->parentCommentId
            ? null
            : PostCommentId::fromString($this->parentCommentId);
    }

    public function isReply(): bool
    {
        return null !== $this->parentCommentId;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function edit(string $body, \DateTimeImmutable $now): void
    {
        $this->body = trim($body);
        $this->editedAt = $now;
    }

    public function delete(\DateTimeImmutable $now): void
    {
        $this->deletedAt ??= $now;
    }

    public function replyAdded(): void
    {
        ++$this->replyCount;
    }
}
