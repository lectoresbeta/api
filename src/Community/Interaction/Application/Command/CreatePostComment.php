<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Application\Command;

use LectoresBeta\Community\Mention\Application\DTO\MentionInput;

/**
 * Comentar una publicación, o responder a un comentario suyo
 * (`FEAT-COM-006`, `FEAT-COM-031`).
 *
 * Un solo comando para los dos: una respuesta **es** un comentario con padre
 * (`RN-1`), y separarlos duplicaría las cuatro comprobaciones que comparten.
 */
final readonly class CreatePostComment
{
    public function __construct(
        public string $postId,
        public string $authorId,
        public ?string $body,
        public ?string $parentCommentId = null,
        /** @var list<MentionInput> */
        public array $mentions = [],
    ) {
    }
}
