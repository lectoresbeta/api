<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Application\Handler;

use LectoresBeta\Community\Interaction\Application\Command\DeleteChapterComment;
use LectoresBeta\Community\Interaction\Application\Service\ChapterCounters;
use LectoresBeta\Community\Interaction\Domain\Exception\CommentNotFound;
use LectoresBeta\Community\Interaction\Domain\Repository\ChapterCommentRepository;
use LectoresBeta\Community\Interaction\Domain\ValueObject\ChapterCommentId;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Retirar un comentario propio de un capítulo (`FEAT-COM-036`).
 *
 * **Retirar un comentario raíz se lleva sus respuestas**, igual que en el
 * muro: lo contrario dejaría respuestas colgando de algo que ya no está,
 * contestando a una pregunta que nadie puede leer.
 *
 * El contador del capítulo baja por todo lo que se va: cuenta la conversación
 * entera, así que retirar un hilo de cinco respuestas le resta seis.
 *
 * **El autor de la obra no borra comentarios ajenos.** Ocultar lo que le
 * dicen es otra funcionalidad (`FEAT-FBK-007`), con otra semántica: ahí el
 * comentario sigue existiendo para quien lo escribió.
 */
final readonly class DeleteChapterCommentHandler
{
    public function __construct(
        private ChapterCommentRepository $comments,
        private ChapterCounters $counters,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(DeleteChapterComment $command): void
    {
        try {
            $comment = $this->comments->ofId(ChapterCommentId::fromString($command->commentId));
        } catch (InvalidValue) {
            throw CommentNotFound::create();
        }

        if (null === $comment || !$comment->isBy(MemberId::fromString($command->memberId))) {
            throw CommentNotFound::create();
        }

        $isReply = null !== $comment->parentCommentId();
        $replies = $isReply ? [] : $this->comments->allRepliesOf($comment->id());
        $parent = $isReply
            ? $this->comments->ofId($comment->parentCommentId() ?? throw CommentNotFound::create())
            : null;

        $now = $this->clock->now();

        $this->session->execute(function () use ($comment, $replies, $parent, $now): void {
            $comment->deleted($now);
            $this->comments->save($comment);

            foreach ($replies as $reply) {
                $reply->deleted($now);
                $this->comments->save($reply);
            }

            if (null !== $parent) {
                $parent->replyRemoved();
                $this->comments->save($parent);
            }

            $engagement = $this->counters->of($comment->chapterId());

            for ($gone = \count($replies) + 1; $gone > 0; --$gone) {
                $engagement->commentRemoved();
            }

            $this->counters->save($engagement);
        });
    }
}
