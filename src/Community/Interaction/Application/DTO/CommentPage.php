<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Application\DTO;

/**
 * Una página de comentarios y dónde empieza la siguiente.
 *
 * Como el muro: puede venir más corta que el límite si el perfil de algún
 * autor ha dejado de ser visible, y lo que dice si hay más es `nextCursor`.
 */
final readonly class CommentPage
{
    /**
     * @param list<CommentCard> $comments
     */
    public function __construct(
        public array $comments,
        public ?string $nextCursor,
    ) {
    }
}
