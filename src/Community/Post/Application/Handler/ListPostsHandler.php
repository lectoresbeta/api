<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Application\Handler;

use LectoresBeta\Community\Post\Application\DTO\PostCard;
use LectoresBeta\Community\Post\Application\DTO\PostPage;
use LectoresBeta\Community\Post\Application\Query\ListPosts;
use LectoresBeta\Community\Post\Domain\Entity\Post;
use LectoresBeta\Community\Post\Domain\Repository\PostRepository;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Relationship\Domain\Repository\UserBlockRepository;
use LectoresBeta\Community\Subscription\Domain\Repository\AuthorSubscriptionRepository;
use LectoresBeta\Shared\Domain\Exception\InvalidCursor;
use LectoresBeta\Shared\Domain\Pagination\Cursor;
use LectoresBeta\Shared\Domain\Pagination\PageSize;
use LectoresBeta\User\Account\Application\Contract\VisibleProfiles;

/**
 * El muro (`FEAT-COM-001`).
 *
 * **El filtro de audiencia va en la consulta** (`RN-2`). Traer lo que no se
 * puede ver para descartarlo después rompería la paginación —una página de
 * veinte devolvería doce sin motivo— y dejaría el texto de alguien viajando
 * por dentro del servidor, a un volcado de distancia de salir.
 *
 * El perfil ajeno es un **techo** sobre el muro, igual que en las listas de
 * seguidores: quien no es visible para quien mira no aparece, y su
 * publicación tampoco. Un muro no es solo una lista de textos, es una lista
 * de personas diciendo cosas, y sin la persona no hay tarjeta que pintar.
 */
final readonly class ListPostsHandler
{
    public function __construct(
        private PostRepository $posts,
        private AuthorSubscriptionRepository $subscriptions,
        private UserBlockRepository $blocks,
        private VisibleProfiles $profiles,
    ) {
    }

    public function __invoke(ListPosts $query): PostPage
    {
        $reader = MemberId::fromString($query->readerId);
        $limit = PageSize::of($query->limit);
        $after = null === $query->cursor || '' === $query->cursor
            ? null
            : Cursor::decode($query->cursor) ?? throw InvalidCursor::create();

        $found = $this->posts->wallFor(
            $reader,
            $this->subscriptions->followedBy($reader),
            $this->blocks->involving($reader),
            $after,
            $limit,
        );

        $rows = \array_slice($found, 0, $limit);
        $last = end($rows);

        $authors = $this->profiles->visibleTo($query->readerId, array_values(array_unique(
            array_map(static fn (Post $post): string => $post->authorId()->value(), $rows),
        )));

        $attachments = $this->posts->attachmentsOf(array_map(
            static fn (Post $post): string => $post->id()->value(),
            $rows,
        ));

        $cards = [];

        foreach ($rows as $post) {
            $author = $authors[$post->authorId()->value()] ?? null;

            if (null === $author) {
                continue;
            }

            $cards[] = new PostCard(
                $post->id()->value(),
                $author,
                $post->body(),
                $post->type()->value,
                $post->format()->value,
                $post->audience()->value,
                // La dirección de la imagen apunta a la publicación, no al
                // almacén: una publicación para seguidores no puede quedar
                // protegida solo por lo difícil que es adivinar una clave.
                isset($attachments[$post->id()->value()])
                    ? \sprintf('/api/v1/posts/%s/image', $post->id()->value())
                    : null,
                $post->linkUrl(),
                $post->workId()?->value(),
                $post->commentCount(),
                $post->likeCount(),
                $post->repostCount(),
                $post->wasEdited(),
                $post->createdAt(),
            );
        }

        return new PostPage(
            $cards,
            \count($found) > $limit && false !== $last
                ? Cursor::of($last->createdAt(), $last->id()->value())->encode()
                : null,
        );
    }
}
