<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Application\Command;

/**
 * Cambiar el texto de una publicación propia (`FEAT-COM-002` `RN-13`).
 *
 * Solo el texto: ni la audiencia ni el adjunto, que se fijan al publicar.
 */
final readonly class EditPost
{
    public function __construct(
        public string $postId,
        public string $authorId,
        public ?string $body,
    ) {
    }
}
