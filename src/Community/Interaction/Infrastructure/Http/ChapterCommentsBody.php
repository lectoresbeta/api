<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Infrastructure\Http;

use LectoresBeta\Community\Interaction\Application\DTO\ChapterCommentCard;
use Symfony\Component\HttpFoundation\Request;

/**
 * La forma de una lista de comentarios de capítulo en HTTP (`FEAT-COM-036`).
 */
final readonly class ChapterCommentsBody
{
    /**
     * @param list<ChapterCommentCard> $comments
     *
     * @return array<string, mixed>
     */
    public static function of(array $comments, ?string $nextCursor): array
    {
        return [
            'comments' => array_map(
                static fn (ChapterCommentCard $card): array => [
                    'commentId' => $card->commentId,
                    'author' => [
                        'userId' => $card->author->userId,
                        'username' => $card->author->username,
                        'name' => $card->author->name,
                        'avatarUrl' => $card->author->avatarUrl,
                    ],
                    'body' => $card->body,
                    'parentCommentId' => $card->parentCommentId,
                    'replyCount' => $card->replyCount,
                    'mine' => $card->mine,
                    'createdAt' => $card->createdAt->format(\DATE_ATOM),
                ],
                $comments,
            ),
            'pageInfo' => [
                'nextCursor' => $nextCursor,
                'hasNextPage' => null !== $nextCursor,
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
}
