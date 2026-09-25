<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Domain\Repository;

use LectoresBeta\Community\Interaction\Domain\Entity\PostCommentLike;
use LectoresBeta\Community\Interaction\Domain\ValueObject\PostCommentId;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;

interface PostCommentLikeRepository
{
    public function save(PostCommentLike $like): void;

    public function remove(PostCommentLike $like): void;

    public function between(MemberId $memberId, PostCommentId $commentId): ?PostCommentLike;

    /**
     * @param list<string> $commentIds
     *
     * @return list<string>
     */
    public function likedAmong(MemberId $memberId, array $commentIds): array;

    public function removeAllOf(PostCommentId $commentId): void;
}
