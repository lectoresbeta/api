<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Domain\Entity;

use LectoresBeta\Community\Post\Domain\ValueObject\PostAttachmentId;
use LectoresBeta\Community\Post\Domain\ValueObject\PostId;

/**
 * A file attached to a post.
 *
 * Its own table because whether a post may carry several is still open
 * (`C-5`). One row per file answers both shapes; a column on `post` would
 * only answer one.
 *
 * Files live in external storage through the `FileStorage` port. What is in
 * the database is a reference.
 */
class PostAttachment
{
    private string $id;

    private string $postId;

    private string $url;

    private string $mediaType;

    private int $position;

    public function __construct(
        PostAttachmentId $id,
        PostId $postId,
        string $url,
        string $mediaType,
        int $position = 0,
    ) {
        $this->id = $id->value();
        $this->postId = $postId->value();
        $this->url = $url;
        $this->mediaType = $mediaType;
        $this->position = $position;
    }

    public function id(): PostAttachmentId
    {
        return PostAttachmentId::fromString($this->id);
    }

    public function postId(): PostId
    {
        return PostId::fromString($this->postId);
    }

    public function url(): string
    {
        return $this->url;
    }

    public function mediaType(): string
    {
        return $this->mediaType;
    }
}
