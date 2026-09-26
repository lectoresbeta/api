<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Application\Handler;

use LectoresBeta\Community\Interaction\Application\DTO\ChapterCommentCard;
use LectoresBeta\Community\Interaction\Application\Query\ListChapterCommentReplies;
use LectoresBeta\Community\Interaction\Application\Query\ListChapterComments;
use LectoresBeta\Community\Interaction\Application\Service\ReadableChapter;
use LectoresBeta\Community\Interaction\Domain\Entity\ChapterComment;
use LectoresBeta\Community\Interaction\Domain\Exception\CommentNotFound;
use LectoresBeta\Community\Interaction\Domain\Repository\ChapterCommentRepository;
use LectoresBeta\Community\Interaction\Domain\ValueObject\ChapterCommentId;
use LectoresBeta\Community\Interaction\Domain\ValueObject\ChapterId;
use LectoresBeta\Shared\Domain\Exception\InvalidCursor;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Pagination\Cursor;
use LectoresBeta\Shared\Domain\Pagination\PageSize;
use LectoresBeta\User\Account\Application\Contract\VisibleProfiles;

/**
 * Los comentarios de un capítulo, del más reciente al más antiguo
 * (`FEAT-COM-036`).
 *
 * **No «Más relevantes»**, que es lo que enseña la maqueta: ordenar por
 * relevancia necesita una fórmula que el material de partida no define
 * (`CM-4`), la misma que tiene bloqueados los tres rankings y el orden del
 * muro. Un orden inventado aquí sería un cuarto sitio del que desdecirse.
 *
 * **Solo lee quien puede leer el capítulo.** Los comentarios no tienen
 * audiencia propia: heredan entera la de la obra, y preguntarlo así —y no
 * «dame los comentarios de este identificador»— es lo que impide que esta
 * lista sea la puerta trasera de una obra inédita.
 *
 * El techo de audiencia del perfil **no** se aplica aquí: quien cerró sus
 * comentarios no ha escondido la conversación que ya existe.
 */
final readonly class ListChapterCommentsHandler
{
    public function __construct(
        private ReadableChapter $reachable,
        private ChapterCommentRepository $comments,
        private VisibleProfiles $profiles,
    ) {
    }

    /**
     * @return array{comments: list<ChapterCommentCard>, nextCursor: ?string}
     */
    public function __invoke(ListChapterComments $query): array
    {
        $access = $this->reachable->seenBy($query->chapterId, $query->readerId);
        $limit = PageSize::of($query->limit);

        $found = $this->comments->topLevelOf(
            ChapterId::fromString($access->chapterId),
            $this->after($query->cursor),
            $limit,
        );

        return $this->page($found, $limit, $query->readerId);
    }

    /**
     * Las respuestas de un comentario, de la más antigua a la más reciente:
     * un hilo se lee en el orden en que se dijo.
     *
     * **Se comprueba el capítulo, no el comentario.** Un comentario no tiene
     * audiencia propia: hereda entera la de la obra, y preguntar así es lo
     * que impide que pedir respuestas sea la puerta trasera de un hilo que
     * cuelga de un capítulo que no se puede ver.
     *
     * @return array{comments: list<ChapterCommentCard>, nextCursor: ?string}
     */
    public function replies(ListChapterCommentReplies $query): array
    {
        try {
            $root = $this->comments->ofId(ChapterCommentId::fromString($query->commentId));
        } catch (InvalidValue) {
            throw CommentNotFound::create();
        }

        if (null === $root) {
            throw CommentNotFound::create();
        }

        $this->reachable->seenBy($root->chapterId()->value(), $query->readerId);

        $size = PageSize::of($query->limit);

        return $this->page(
            $this->comments->repliesOf($root->id(), $this->after($query->cursor), $size),
            $size,
            $query->readerId,
        );
    }

    private function after(?string $cursor): ?Cursor
    {
        if (null === $cursor || '' === $cursor) {
            return null;
        }

        return Cursor::decode($cursor) ?? throw InvalidCursor::create();
    }

    /**
     * @param list<ChapterComment> $found
     *
     * @return array{comments: list<ChapterCommentCard>, nextCursor: ?string}
     */
    private function page(array $found, int $limit, string $readerId): array
    {
        $rows = \array_slice($found, 0, $limit);
        $last = end($rows);

        // De golpe y no fila a fila: quién es visible lo decide `User`, y una
        // llamada por comentario sería un N+1 escondido tras un contrato.
        $authors = $this->profiles->visibleTo($readerId, array_values(array_unique(
            array_map(static fn (ChapterComment $comment): string => $comment->authorId()->value(), $rows),
        )));

        $cards = [];

        foreach ($rows as $comment) {
            $author = $authors[$comment->authorId()->value()] ?? null;

            // Quien ha dejado de ser visible deja un hueco: lo que dice si
            // hay más es el cursor, no cuántas filas llegaron.
            if (null === $author) {
                continue;
            }

            $cards[] = new ChapterCommentCard(
                $comment->id()->value(),
                $author,
                $comment->body(),
                $comment->parentCommentId()?->value(),
                $comment->replyCount(),
                $comment->authorId()->value() === $readerId,
                $comment->createdAt(),
            );
        }

        return [
            'comments' => $cards,
            'nextCursor' => \count($found) > $limit && false !== $last
                ? Cursor::of($last->createdAt(), $last->id()->value())->encode()
                : null,
        ];
    }
}
