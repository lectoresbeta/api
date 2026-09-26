<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Application\Handler;

use LectoresBeta\Community\Interaction\Application\Command\LikePostComment;
use LectoresBeta\Community\Interaction\Application\Service\ReachableComment;
use LectoresBeta\Community\Interaction\Domain\Entity\PostCommentLike;
use LectoresBeta\Community\Interaction\Domain\Repository\PostCommentLikeRepository;
use LectoresBeta\Community\Interaction\Domain\Repository\PostCommentRepository;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Lo mismo sobre un comentario (`FEAT-COM-030`).
 *
 * El objetivo es **el comentario que se señala**, respuesta incluida: subir
 * el contador de su hilo sería contar en el sitio equivocado, y la pantalla
 * enseña un número por comentario.
 *
 * Un comentario no tiene audiencia propia —hereda entera la de su
 * publicación—, así que `ReachableComment` es quien decide si se alcanza.
 */
final readonly class LikePostCommentHandler
{
    public function __construct(
        private ReachableComment $reachable,
        private PostCommentRepository $comments,
        private PostCommentLikeRepository $likes,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function like(LikePostComment $command): int
    {
        $comment = $this->reachable->itself($command->commentId, $command->memberId);
        $member = MemberId::fromString($command->memberId);

        if (null !== $this->likes->between($member, $comment->id())) {
            return $comment->likeCount();
        }

        $this->session->execute(function () use ($comment, $member): void {
            $this->likes->save(new PostCommentLike($comment->id(), $member, $this->clock->now()));
            $comment->liked();
            $this->comments->save($comment);
        });

        return $comment->likeCount();
    }

    public function unlike(LikePostComment $command): int
    {
        $comment = $this->reachable->itself($command->commentId, $command->memberId);
        $member = MemberId::fromString($command->memberId);
        $like = $this->likes->between($member, $comment->id());

        if (null === $like) {
            return $comment->likeCount();
        }

        $this->session->execute(function () use ($comment, $like): void {
            $this->likes->remove($like);
            $comment->unliked();
            $this->comments->save($comment);
        });

        return $comment->likeCount();
    }
}
