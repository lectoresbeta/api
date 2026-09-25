<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Application\Command;

/**
 * Retirar una publicación propia (`FEAT-COM-002` `RN-13`).
 */
final readonly class DeletePost
{
    public function __construct(
        public string $postId,
        public string $authorId,
    ) {
    }
}
