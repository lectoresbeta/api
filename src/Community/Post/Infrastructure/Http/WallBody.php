<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Infrastructure\Http;

use LectoresBeta\Community\Mention\Application\DTO\MentionView;
use LectoresBeta\Community\Post\Application\DTO\PostCard;
use LectoresBeta\Community\Post\Application\DTO\PostPage;
use LectoresBeta\Community\Post\Domain\ValueObject\PostFilters;
use LectoresBeta\Work\Manuscript\Application\Contract\WorkCard;
use Symfony\Component\HttpFoundation\Request;

/**
 * La forma del muro en HTTP (`FEAT-COM-001`).
 *
 * Cada tarjeta lleva el autor **resuelto**: una lista de identificadores
 * obligaría al cliente a una petición por tarjeta para poder pintarla.
 *
 * `hasNextPage` sale del cursor y no de cuántas tarjetas llegaron, por la
 * misma razón que en las listas de personas: el perfil de un autor que ha
 * dejado de ser visible deja un hueco, y una página corta no significa que se
 * haya acabado.
 */
final readonly class WallBody
{
    /**
     * @return array<string, mixed>
     */
    public static function of(PostPage $page): array
    {
        return [
            'posts' => array_map(self::card(...), $page->posts),
            'pageInfo' => [
                'nextCursor' => $page->nextCursor,
                'hasNextPage' => null !== $page->nextCursor,
            ],
        ];
    }

    public static function cursor(Request $request): ?string
    {
        $value = $request->query->get('cursor');

        return \is_string($value) && '' !== $value ? $value : null;
    }

    public static function limit(Request $request): ?int
    {
        return $request->query->has('limit') ? $request->query->getInt('limit') : null;
    }

    /**
     * Lo que el usuario ha acotado (`FEAT-COM-009`), leído de la query string.
     *
     * Aquí se valida la **sintaxis** —que la fecha sea una fecha y el tipo
     * uno de los cuatro— y ahí se acaba lo que sabe el transporte: lo que
     * cruza a Application es un objeto con campos tipados.
     */
    public static function filters(Request $request): PostFilters
    {
        return PostFilters::of(
            self::text($request, 'type'),
            self::text($request, 'q'),
            self::text($request, 'authorId'),
            self::text($request, 'from'),
            self::text($request, 'to'),
        );
    }

    private static function text(Request $request, string $field): ?string
    {
        $value = $request->query->get($field);

        return \is_string($value) && '' !== $value ? $value : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function work(?WorkCard $work): ?array
    {
        if (null === $work) {
            return null;
        }

        return [
            'workId' => $work->workId,
            'authorId' => $work->authorId,
            'title' => $work->title,
            'synopsis' => $work->synopsis,
            'status' => $work->status,
            'chapterCount' => $work->chapterCount,
            'readingMinutes' => $work->readingMinutes,
            'adultsOnly' => $work->adultsOnly,
            'contentWarnings' => $work->contentWarnings,
            'genres' => $work->genres,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function card(PostCard $post): array
    {
        return [
            'postId' => $post->postId,
            'author' => [
                'userId' => $post->author->userId,
                'username' => $post->author->username,
                'name' => $post->author->name,
                'avatarUrl' => $post->author->avatarUrl,
            ],
            'body' => $post->body,
            'type' => $post->type,
            'format' => $post->format,
            'audience' => $post->audience,
            'imageUrl' => $post->imageUrl,
            'linkUrl' => $post->linkUrl,
            'workId' => $post->workId,
            // La tarjeta viva de la obra citada (`FEAT-COM-028`). `workId`
            // se queda, y no sobra: dice **qué obra citó quien publicó**,
            // aunque hoy ya no se pueda enseñar.
            'work' => self::work($post->work),
            'commentCount' => $post->commentCount,
            'likeCount' => $post->likeCount,
            'likedByViewer' => $post->likedByViewer,
            'repostCount' => $post->repostCount,
            'edited' => $post->edited,
            'createdAt' => $post->createdAt->format(\DATE_ATOM),
            'repostedBy' => null === $post->repostedBy ? null : [
                'userId' => $post->repostedBy->userId,
                'username' => $post->repostedBy->username,
                'name' => $post->repostedBy->name,
                'avatarUrl' => $post->repostedBy->avatarUrl,
            ],
            'repostComment' => $post->repostComment,
            'repostedAt' => $post->repostedAt?->format(\DATE_ATOM),
            'mentions' => array_map(
                static fn (MentionView $mention): array => [
                    'userId' => $mention->userId,
                    'name' => $mention->name,
                    'position' => $mention->position,
                ],
                $post->mentions,
            ),
        ];
    }
}
