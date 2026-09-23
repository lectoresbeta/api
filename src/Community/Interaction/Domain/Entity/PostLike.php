<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Domain\Entity;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Post\Domain\ValueObject\PostId;

/**
 * Support for a post.
 *
 * Kept apart from the emoji reaction because the sources list them as two
 * mechanisms (`RN-4`) — though the home design only shows this one, so
 * whether both survive is in doubt (`H-5`, `CM-1`). Two tables can be merged
 * later; one table that meant two things could not be told apart.
 */
class PostLike
{
    private string $postId;

    private string $memberId;

    private \DateTimeImmutable $likedAt;

    public function __construct(PostId $postId, MemberId $memberId, \DateTimeImmutable $now)
    {
        $this->postId = $postId->value();
        $this->memberId = $memberId->value();
        $this->likedAt = $now;
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
