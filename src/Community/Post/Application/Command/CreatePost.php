<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Application\Command;

/**
 * Publicar algo en el muro (`FEAT-COM-002`).
 *
 * El autor **no viaja en la petición**, viaja aquí desde la sesión (`RN-2`).
 * Y el formato tampoco se acepta del cliente: se deriva del adjunto (`RN-12`),
 * porque pedirle que declare algo que ya está diciendo solo abre la puerta a
 * que lo diga mal.
 */
final readonly class CreatePost
{
    public function __construct(
        public string $authorId,
        public ?string $body,
        public ?string $type,
        public ?string $audience,
        public ?string $image,
        public ?string $linkUrl,
        public ?string $workId,
    ) {
    }
}
