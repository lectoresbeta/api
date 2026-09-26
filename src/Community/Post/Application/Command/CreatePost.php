<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Application\Command;

use LectoresBeta\Community\Mention\Application\DTO\MentionInput;

/**
 * Publicar algo en el muro (`FEAT-COM-002`).
 *
 * Las menciones llegan como **identificadores con su posición**, no como
 * nombres dentro del texto (`FEAT-COM-032` `RN-7`): si el servidor resolviera
 * la mención a partir de lo escrito, cualquiera podría fabricar una que
 * pareciera apuntar a otra persona.
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
        /** @var list<MentionInput> */
        public array $mentions = [],
    ) {
    }
}
