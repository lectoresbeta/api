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

    /**
     * Cuántos apoyos lleva (`FEAT-COM-030` `RN-3`).
     *
     * En la fila y no contado al listar, por lo mismo que el de la
     * publicación: una lista que hace un `COUNT` por comentario se degrada
     * justo cuando la conversación empieza a valer la pena.
     */
    private int $likeCount = 0;

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

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function replyCount(): int
    {
        return $this->replyCount;
    }

    public function wasEdited(): bool
    {
        return null !== $this->editedAt;
    }

    public function likeCount(): int
    {
        return $this->likeCount;
    }

    public function liked(): void
    {
        ++$this->likeCount;
    }

    public function unliked(): void
    {
        $this->likeCount = max(0, $this->likeCount - 1);
    }

    public function isDeleted(): bool
    {
        return null !== $this->deletedAt;
    }

    /**
     * Un texto idéntico **no marca el comentario como editado**, igual que en
     * una publicación: la marca existe para avisar a quien lee, no para
     * contar pulsaciones.
     */
    public function edit(string $body, \DateTimeImmutable $now): bool
    {
        $body = trim($body);

        if ($body === $this->body) {
            return false;
        }

        $this->body = $body;
        $this->editedAt = $now;

        return true;
    }

    public function delete(\DateTimeImmutable $now): void
    {
        $this->deletedAt ??= $now;
    }

    public function replyAdded(): void
    {
        ++$this->replyCount;
    }

    public function replyRemoved(): void
    {
        $this->replyCount = max(0, $this->replyCount - 1);
    }
}
