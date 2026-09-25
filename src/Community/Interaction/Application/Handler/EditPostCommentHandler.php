<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Application\Handler;

use LectoresBeta\Community\Interaction\Application\Command\EditPostComment;
use LectoresBeta\Community\Interaction\Domain\Exception\CommentNotFound;
use LectoresBeta\Community\Interaction\Domain\Repository\PostCommentRepository;
use LectoresBeta\Community\Interaction\Domain\ValueObject\CommentBody;
use LectoresBeta\Community\Interaction\Domain\ValueObject\PostCommentId;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Cambiar el texto de un comentario propio (`FEAT-COM-006`, `I-3`).
 *
 * Mismo criterio que con una publicación: solo el texto, queda marcado como
 * editado, y el de otra persona **no existe** —`404`, no `403`—, porque un
 * permiso denegado confirmaría que está ahí.
 *
 * Un comentario no puede quedarse vacío: no lleva adjunto, así que sin texto
 * no hay comentario. Vaciarlo del todo es eliminarlo, y para eso hay una
 * operación que dice lo que hace.
 */
final readonly class EditPostCommentHandler
{
    public function __construct(
        private PostCommentRepository $comments,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(EditPostComment $command): void
    {
        try {
            $comment = $this->comments->ofId(PostCommentId::fromString($command->commentId));
        } catch (InvalidValue) {
            throw CommentNotFound::create();
        }

        if (null === $comment || $comment->authorId()->value() !== $command->authorId) {
            throw CommentNotFound::create();
        }

        if (!$comment->edit(CommentBody::fromString($command->body)->value(), $this->clock->now())) {
            return;
        }

        $this->session->execute(function () use ($comment): void {
            $this->comments->save($comment);
        });
    }
}
