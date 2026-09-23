<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Domain\Entity;

use LectoresBeta\Community\Interaction\Domain\ValueObject\CommentMentionId;
use LectoresBeta\Community\Interaction\Domain\ValueObject\PostCommentId;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;

/**
 * Somebody named in a comment (`FEAT-COM-032`).
 *
 * Stored as a `UserId` and never as text (`RN-10`). Usernames change, and
 * with names being recycled after 30 days a stored string could end up
 * pointing at a different person entirely.
 *
 * A mention grants no access to anything, and nobody is told about content
 * they cannot see (`RN-9`).
 */
class CommentMention
{
    private string $id;

    private string $commentId;

    private string $mentionedUserId;

    /** Character offset in the body, so the client can render the link. */
    private int $position;

    public function __construct(
        CommentMentionId $id,
        PostCommentId $commentId,
        MemberId $mentionedUserId,
        int $position,
    ) {
        $this->id = $id->value();
        $this->commentId = $commentId->value();
        $this->mentionedUserId = $mentionedUserId->value();
        $this->position = $position;
    }

    public function id(): CommentMentionId
    {
        return CommentMentionId::fromString($this->id);
    }

    public function commentId(): PostCommentId
    {
        return PostCommentId::fromString($this->commentId);
    }

    public function mentionedUserId(): MemberId
    {
        return MemberId::fromString($this->mentionedUserId);
    }
}
