<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Application\Handler;

use LectoresBeta\Community\Interaction\Application\Command\CreatePostComment;
use LectoresBeta\Community\Interaction\Domain\Entity\PostComment;
use LectoresBeta\Community\Interaction\Domain\Event\PostCommented;
use LectoresBeta\Community\Interaction\Domain\Exception\CommentNotFound;
use LectoresBeta\Community\Interaction\Domain\Repository\PostCommentRepository;
use LectoresBeta\Community\Interaction\Domain\ValueObject\CommentBody;
use LectoresBeta\Community\Interaction\Domain\ValueObject\PostCommentId;
use LectoresBeta\Community\Mention\Application\Service\RecordMentions;
use LectoresBeta\Community\Mention\Domain\Enum\MentionSubject;
use LectoresBeta\Community\Post\Application\Service\VisiblePost;
use LectoresBeta\Community\Post\Domain\Repository\PostRepository;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Comentar una publicación y responder a un comentario (`FEAT-COM-006`,
 * `FEAT-COM-031`).
 *
 * **Solo comenta quien puede ver la publicación** (`RN-1`), y esa es la regla
 * que hay que vigilar: con audiencias distintas de «cualquiera», comentar es
 * la otra vía por la que alguien podría tocar contenido que no debería ver.
 * Por eso se pregunta por `VisiblePost` y no por el repositorio: la
 * comprobación va dentro de la respuesta, no al lado.
 *
 * **Los hilos son planos** (`FEAT-COM-031` `RN-2`): responder a una respuesta
 * cuelga del comentario raíz, nunca de la respuesta. La invariante se resuelve
 * aquí, en un sitio, y el cliente no necesita saber cuál es el raíz.
 *
 * Los dos contadores suben en la misma transacción que el comentario: el de
 * la publicación cuenta **toda la conversación**, respuestas incluidas, y el
 * del comentario cuenta las suyas.
 */
final readonly class CreatePostCommentHandler
{
    public function __construct(
        private VisiblePost $visible,
        private PostRepository $posts,
        private PostCommentRepository $comments,
        private RecordMentions $mentions,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(CreatePostComment $command): string
    {
        $post = $this->visible->to($command->postId, $command->authorId);
        $body = CommentBody::fromString($command->body);
        $author = MemberId::fromString($command->authorId);

        $parent = $this->root($command->parentCommentId, $command->postId);

        $comment = new PostComment(
            PostCommentId::generate(),
            $post->id(),
            $author,
            $body->value(),
            $now = $this->clock->now(),
            $parent?->id(),
        );

        $mentions = $this->mentions->of(
            MentionSubject::COMMENT,
            $comment->id()->value(),
            $command->mentions,
            $comment->body(),
        );

        $this->session->execute(function () use ($comment, $post, $parent, $mentions): void {
            $this->comments->save($comment);

            $post->commentAdded();
            $this->posts->save($post);

            if (null !== $parent) {
                $parent->replyAdded();
                $this->comments->save($parent);
            }

            foreach ($mentions as $mention) {
                $this->mentions->save($mention);
            }
        });

        $this->events->publish(new PostCommented(
            EventId::generate(),
            $post->id(),
            $comment->id(),
            $post->authorId(),
            $author,
            $parent?->authorId(),
            $now,
        ));

        $this->mentions->announce($mentions, $post, $author, $now);

        return $comment->id()->value();
    }

    /**
     * El comentario del que cuelga la respuesta, **siempre de primer nivel**.
     *
     * Si lo que llega es una respuesta, se sube a su raíz. Es lo que mantiene
     * la invariante sin pedirle al cliente que sepa cuál es, y lo que hace que
     * un hilo se pueda paginar.
     */
    private function root(?string $parentCommentId, string $postId): ?PostComment
    {
        if (null === $parentCommentId || '' === $parentCommentId) {
            return null;
        }

        try {
            $parent = $this->comments->ofId(PostCommentId::fromString($parentCommentId));
        } catch (InvalidValue) {
            throw CommentNotFound::create();
        }

        if (null === $parent || $parent->postId()->value() !== $postId) {
            throw CommentNotFound::create();
        }

        if (!$parent->isReply()) {
            return $parent;
        }

        $root = $this->comments->ofId($parent->parentCommentId() ?? throw CommentNotFound::create());

        return $root ?? throw CommentNotFound::create();
    }
}
