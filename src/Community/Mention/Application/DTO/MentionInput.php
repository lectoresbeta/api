<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Mention\Application\DTO;

/**
 * Una mención tal y como la manda el cliente (`FEAT-COM-032` `RN-7`).
 *
 * Lleva **el identificador de quien se seleccionó**, no el nombre que se
 * escribió. Si el servidor resolviera la mención a partir del texto,
 * cualquiera podría fabricar una que pareciera apuntar a otra persona
 * escribiendo su nombre a mano.
 */
final readonly class MentionInput
{
    public function __construct(
        public string $userId,
        public int $position,
    ) {
    }
}
