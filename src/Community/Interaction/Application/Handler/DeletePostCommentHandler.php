<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Application\Handler;

use LectoresBeta\Community\Interaction\Application\Command\DeletePostComment;
use LectoresBeta\Community\Interaction\Domain\Exception\CommentNotFound;
use LectoresBeta\Community\Interaction\Domain\Repository\PostCommentLikeRepository;
use LectoresBeta\Community\Interaction\Domain\Repository\PostCommentRepository;
use LectoresBeta\Community\Interaction\Domain\ValueObject\PostCommentId;
use LectoresBeta\Community\Post\Domain\Repository\PostRepository;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Retirar un comentario propio (`FEAT-COM-006`, `I-3`).
 *
 * **Eliminar un comentario raíz elimina sus respuestas** (`FEAT-COM-031`
 * `RN-7`): lo contrario dejaría respuestas colgando de algo que ya no está,
 * contestando a una pregunta que nadie puede leer.
 *
 * Y los contadores bajan por lo que se va: el de la publicación cuenta toda
 * la conversación, así que retirar un hilo de cinco respuestas le resta seis.
 */
final readonly class DeletePostCommentHandler
{
    public function __construct(
        private PostCommentRepository $comments,
        private PostCommentLikeRepository $likes,
        private PostRepository $posts,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(DeletePostComment $command): void
    {
        try {
            $comment = $this->comments->ofId(PostCommentId::fromString($command->commentId));
        } catch (InvalidValue) {
            throw CommentNotFound::create();
        }

        if (null === $comment || $comment->authorId()->value() !== $command->authorId) {
            throw CommentNotFound::create();
        }

        $replies = $comment->isReply() ? [] : $this->comments->allRepliesOf($comment->id());
        $parent = $comment->isReply()
            ? $this->comments->ofId($comment->parentCommentId() ?? throw CommentNotFound::create())
            : null;

        $now = $this->clock->now();

        $this->session->execute(function () use ($comment, $replies, $parent, $now): void {
            $comment->delete($now);
            $this->comments->save($comment);
            $this->likes->removeAllOf($comment->id());

            foreach ($replies as $reply) {
                $reply->delete($now);
                $this->comments->save($reply);
                $this->likes->removeAllOf($reply->id());
            }

            if (null !== $parent) {
                $parent->replyRemoved();
                $this->comments->save($parent);
            }

            $post = $this->posts->ofId($comment->postId());

            if (null !== $post) {
                // El comentario y todo lo que colgaba de él: el contador de
                // la publicación cuenta la conversación entera.
                for ($gone = \count($replies) + 1; $gone > 0; --$gone) {
                    $post->commentRemoved();
                }

                $this->posts->save($post);
            }
        });
    }
}
