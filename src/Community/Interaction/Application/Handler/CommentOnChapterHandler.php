<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Application\Handler;

use LectoresBeta\Community\Interaction\Application\Command\CommentOnChapter;
use LectoresBeta\Community\Interaction\Application\Service\ChapterCounters;
use LectoresBeta\Community\Interaction\Application\Service\ReadableChapter;
use LectoresBeta\Community\Interaction\Domain\Entity\ChapterComment;
use LectoresBeta\Community\Interaction\Domain\Event\ChapterCommented;
use LectoresBeta\Community\Interaction\Domain\Exception\CommentNotFound;
use LectoresBeta\Community\Interaction\Domain\Repository\ChapterCommentRepository;
use LectoresBeta\Community\Interaction\Domain\ValueObject\ChapterCommentId;
use LectoresBeta\Community\Interaction\Domain\ValueObject\ChapterId;
use LectoresBeta\Community\Interaction\Domain\ValueObject\CommentBody;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Comentar un capítulo y responder a un comentario (`FEAT-COM-036`).
 *
 * > **Comentar un capítulo no es corregirlo.**
 * >
 * > Es la distinción más importante de esta pantalla y la más fácil de
 * > perder de vista. Un comentario es una reacción libre bajo el texto y **no
 * > mueve un solo crédito**; la corrección es la respuesta al cuestionario
 * > del autor, hay una por lector y capítulo, y sí los mueve
 * > (`FEAT-FBK-003`). Conviven en la misma pantalla y pertenecen a contextos
 * > distintos.
 *
 * **Los hilos son planos**, igual que en el muro: responder a una respuesta
 * cuelga del comentario raíz, nunca de la respuesta. El cliente no necesita
 * saber cuál es el raíz — responde a lo que tiene delante y el servidor lo
 * cuelga donde toca.
 *
 * Sin menciones, a diferencia de los comentarios del muro (`FEAT-COM-032`).
 * No es un olvido: nombrar a alguien bajo un capítulo lo arrastraría a una
 * obra que quizá no puede leer, y la regla de que **mencionar no da acceso a
 * nada** obligaría a filtrar cada mención contra la visibilidad de la obra.
 * Se deja fuera hasta que haya una pantalla que lo pida.
 */
final readonly class CommentOnChapterHandler
{
    public function __construct(
        private ReadableChapter $reachable,
        private ChapterCommentRepository $comments,
        private ChapterCounters $counters,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(CommentOnChapter $command): string
    {
        $access = $this->reachable->writtenOnBy($command->chapterId, $command->authorId);
        $body = CommentBody::fromString($command->body);
        $author = MemberId::fromString($command->authorId);
        $chapterId = ChapterId::fromString($access->chapterId);

        $parent = $this->rootOf($command->parentCommentId, $chapterId);

        $comment = new ChapterComment(
            ChapterCommentId::generate(),
            $chapterId,
            $author,
            $body->value(),
            $now = $this->clock->now(),
            $parent?->id(),
        );

        $this->session->execute(function () use ($comment, $chapterId, $parent): void {
            $this->comments->save($comment);

            // El contador del capítulo cuenta **toda la conversación**,
            // respuestas incluidas: es lo que enseña la cabecera.
            $engagement = $this->counters->of($chapterId);
            $engagement->commented();
            $this->counters->save($engagement);

            if (null !== $parent) {
                $parent->replied();
                $this->comments->save($parent);
            }
        });

        $this->events->publish(new ChapterCommented(
            EventId::generate(),
            $chapterId,
            $access->workId,
            $comment->id(),
            MemberId::fromString($access->authorId),
            $author,
            $parent?->authorId(),
            $now,
        ));

        return $comment->id()->value();
    }

    /**
     * El comentario raíz del hilo al que se responde, **dentro de este
     * capítulo**.
     *
     * Comprobar que pertenece al capítulo no es paranoia: sin ello,
     * responder sería la puerta por la que colgar texto de un hilo que no se
     * puede ver, pasando por un capítulo que sí.
     */
    private function rootOf(?string $parentCommentId, ChapterId $chapterId): ?ChapterComment
    {
        if (null === $parentCommentId || '' === $parentCommentId) {
            return null;
        }

        try {
            $parent = $this->comments->ofId(ChapterCommentId::fromString($parentCommentId));
        } catch (InvalidValue) {
            throw CommentNotFound::create();
        }

        if (null === $parent || $parent->chapterId()->value() !== $chapterId->value()) {
            throw CommentNotFound::create();
        }

        $root = $parent->parentCommentId();

        if (null === $root) {
            return $parent;
        }

        $found = $this->comments->ofId($root);

        if (null === $found) {
            throw CommentNotFound::create();
        }

        return $found;
    }
}
