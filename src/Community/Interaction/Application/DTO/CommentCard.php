<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Application\DTO;

use LectoresBeta\Community\Mention\Application\DTO\MentionView;
use LectoresBeta\User\Account\Application\Contract\DirectoryEntry;

/**
 * Un comentario o una respuesta, ya listos para pintar (`FEAT-COM-006`).
 *
 * Lleva **sus acciones** —cuántas respuestas cuelgan, si es mío— para que el
 * cliente no tenga que pedirlas aparte por cada fila.
 */
final readonly class CommentCard
{
    public function __construct(
        public string $commentId,
        public DirectoryEntry $author,
        public string $body,
        public ?string $parentCommentId,
        public int $replyCount,
        public int $likeCount,
        /** Si quien mira lo ha apoyado (`FEAT-COM-030` `RN-4`). */
        public bool $likedByViewer,
        public bool $mine,
        public bool $edited,
        public \DateTimeImmutable $createdAt,
        /** @var list<MentionView> */
        public array $mentions = [],
    ) {
    }
}
