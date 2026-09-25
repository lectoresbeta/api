<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Application\Service;

use LectoresBeta\Community\Interaction\Application\DTO\CommentCard;
use LectoresBeta\Community\Interaction\Application\DTO\CommentPage;
use LectoresBeta\Community\Interaction\Domain\Entity\PostComment;
use LectoresBeta\Community\Interaction\Domain\Exception\UnsupportedCommentSort;
use LectoresBeta\Shared\Domain\Exception\InvalidCursor;
use LectoresBeta\Shared\Domain\Pagination\Cursor;
use LectoresBeta\Shared\Domain\Pagination\PageSize;
use LectoresBeta\User\Account\Application\Contract\VisibleProfiles;

/**
 * Lo que los comentarios y las respuestas hacen igual (`FEAT-COM-006`,
 * `FEAT-COM-031`).
 *
 * Son la misma tabla leída de dos maneras —los de primer nivel de una
 * publicación, y los que cuelgan de uno— así que paginarlas y resolver a sus
 * autores es el mismo trabajo. Escribirlo dos veces serían dos sitios donde
 * equivocarse con el cursor.
 *
 * **`Community` no decide quién es visible.** Le pregunta a `User`, y por la
 * página entera de una vez: una llamada por fila sería un N+1 escondido
 * detrás de un contrato.
 */
final readonly class PageOfComments
{
    public function __construct(private VisibleProfiles $profiles)
    {
    }

    public function size(?int $requested): int
    {
        return PageSize::of($requested);
    }

    public function after(?string $cursor): ?Cursor
    {
        if (null === $cursor || '' === $cursor) {
            return null;
        }

        return Cursor::decode($cursor) ?? throw InvalidCursor::create();
    }

    /**
     * @return 'RECENT'|'OLDEST'
     */
    public function sort(?string $requested): string
    {
        $sort = strtoupper($requested ?? 'RECENT');

        return match ($sort) {
            'RECENT' => 'RECENT',
            'OLDEST' => 'OLDEST',
            default => throw UnsupportedCommentSort::of($sort, ['RECENT', 'OLDEST']),
        };
    }

    /**
     * @param list<PostComment> $found una fila de más que la página, que es
     *                                 cómo se sabe si hay siguiente
     */
    public function of(array $found, int $limit, string $readerId): CommentPage
    {
        $rows = \array_slice($found, 0, $limit);
        $last = end($rows);

        $authors = $this->profiles->visibleTo($readerId, array_values(array_unique(
            array_map(static fn (PostComment $comment): string => $comment->authorId()->value(), $rows),
        )));

        $cards = [];

        foreach ($rows as $comment) {
            $author = $authors[$comment->authorId()->value()] ?? null;

            // Quien ha dejado de ser visible deja un hueco, igual que en el
            // muro: lo que dice si hay más es el cursor, no cuántas filas
            // llegaron.
            if (null === $author) {
                continue;
            }

            $cards[] = new CommentCard(
                $comment->id()->value(),
                $author,
                $comment->body(),
                $comment->parentCommentId()?->value(),
                $comment->replyCount(),
                $comment->authorId()->value() === $readerId,
                $comment->wasEdited(),
                $comment->createdAt(),
            );
        }

        return new CommentPage(
            $cards,
            \count($found) > $limit && false !== $last
                ? Cursor::of($last->createdAt(), $last->id()->value())->encode()
                : null,
        );
    }
}
