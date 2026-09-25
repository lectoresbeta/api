<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Infrastructure\Http;

use LectoresBeta\Community\Mention\Application\DTO\MentionView;
use LectoresBeta\Community\Post\Application\DTO\PostCard;
use LectoresBeta\Community\Post\Application\DTO\PostPage;
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
