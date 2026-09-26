<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Application\DTO;

use LectoresBeta\User\Account\Application\Contract\DirectoryEntry;

/**
 * Un comentario de capítulo, listo para pintar (`FEAT-COM-036`).
 *
 * Más corto que el del muro y a propósito: aquí no hay menciones
 * —nombrar a alguien bajo un capítulo lo arrastraría a una obra que quizá no
 * puede leer— ni apoyos sobre el comentario, que en el muro son
 * `FEAT-COM-030` y aquí no los pide ninguna pantalla.
 */
final readonly class ChapterCommentCard
{
    public function __construct(
        public string $commentId,
        public DirectoryEntry $author,
        public string $body,
        public ?string $parentCommentId,
        public int $replyCount,
        public bool $mine,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
