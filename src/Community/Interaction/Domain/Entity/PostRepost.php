<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Domain\Entity;

use LectoresBeta\Community\Interaction\Domain\ValueObject\PostRepostId;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Post\Domain\ValueObject\PostId;

/**
 * Somebody resharing a post (`FEAT-COM-019`).
 *
 * A repost **does not widen the audience** of the original (`RN-8`): whoever
 * could not see it still cannot. The check is on the original post, not on
 * this row, which is why no audience is copied here.
 */
class PostRepost
{
    private string $id;

    private string $postId;

    private string $memberId;

    private ?string $comment = null;

    private \DateTimeImmutable $createdAt;

    public function __construct(
        PostRepostId $id,
        PostId $postId,
        MemberId $memberId,
        \DateTimeImmutable $now,
        ?string $comment = null,
    ) {
        $this->id = $id->value();
        $this->postId = $postId->value();
        $this->memberId = $memberId->value();
        $this->createdAt = $now;
        $this->comment = $comment;
    }

    public function id(): PostRepostId
    {
        return PostRepostId::fromString($this->id);
    }

    public function postId(): PostId
    {
        return PostId::fromString($this->postId);
    }

    public function memberId(): MemberId
    {
        return MemberId::fromString($this->memberId);
    }
}
