<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Infrastructure\Http;

use LectoresBeta\Community\Interaction\Application\DTO\CommentCard;
use LectoresBeta\Community\Interaction\Application\DTO\CommentPage;
use LectoresBeta\Community\Mention\Application\DTO\MentionView;
use Symfony\Component\HttpFoundation\Request;

/**
 * La forma de una lista de comentarios en HTTP (`FEAT-COM-006`).
 *
 * Cada fila lleva **sus acciones** —cuántas respuestas cuelgan, si es mía—
 * para que el cliente no tenga que pedirlas aparte por cada comentario.
 */
final readonly class CommentsBody
{
    /**
     * @return array<string, mixed>
     */
    public static function of(CommentPage $page): array
    {
        return [
            'comments' => array_map(self::card(...), $page->comments),
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

    public static function sort(Request $request): ?string
    {
        $value = $request->query->get('sort');

        return \is_string($value) && '' !== $value ? $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function card(CommentCard $comment): array
    {
        return [
            'commentId' => $comment->commentId,
            'author' => [
                'userId' => $comment->author->userId,
                'username' => $comment->author->username,
                'name' => $comment->author->name,
                'avatarUrl' => $comment->author->avatarUrl,
            ],
            'body' => $comment->body,
            'parentCommentId' => $comment->parentCommentId,
            'replyCount' => $comment->replyCount,
            'mine' => $comment->mine,
            'edited' => $comment->edited,
            'createdAt' => $comment->createdAt->format(\DATE_ATOM),
            'mentions' => array_map(
                static fn (MentionView $mention): array => [
                    'userId' => $mention->userId,
                    'name' => $mention->name,
                    'position' => $mention->position,
                ],
                $comment->mentions,
            ),
        ];
    }
}
