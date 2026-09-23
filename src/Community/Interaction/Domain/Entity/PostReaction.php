<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Domain\Entity;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Post\Domain\ValueObject\PostId;

/**
 * An emoji reaction. One per member and post: reacting again replaces the
 * previous one (`RN-3`), which is why the key is the pair and the emoji is a
 * plain column.
 *
 * Which emojis are allowed is not decided (`CM-2`), so nothing here validates
 * the value.
 */
class PostReaction
{
    private string $postId;

    private string $memberId;

    private string $emoji;

    private \DateTimeImmutable $reactedAt;

    public function __construct(PostId $postId, MemberId $memberId, string $emoji, \DateTimeImmutable $now)
    {
        $this->postId = $postId->value();
        $this->memberId = $memberId->value();
        $this->emoji = $emoji;
        $this->reactedAt = $now;
    }

    public function postId(): PostId
    {
        return PostId::fromString($this->postId);
    }

    public function memberId(): MemberId
    {
        return MemberId::fromString($this->memberId);
    }

    public function emoji(): string
    {
        return $this->emoji;
    }

    public function changeTo(string $emoji, \DateTimeImmutable $now): void
    {
        $this->emoji = $emoji;
        $this->reactedAt = $now;
    }
}
