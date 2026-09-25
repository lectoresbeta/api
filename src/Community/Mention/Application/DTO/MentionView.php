<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Mention\Application\DTO;

/**
 * Una mención tal y como se sirve (`FEAT-COM-032`).
 *
 * Viaja **aparte del texto**, con la posición donde empieza. El cliente no
 * interpreta la cadena buscando arrobas: que cliente y servidor tengan que
 * coincidir en cómo se parsea un texto es una fuente clásica de
 * discrepancias, y aquí la discrepancia sería un enlace apuntando a quien no
 * es.
 *
 * `userId` y `name` vienen a `null` cuando el mencionado ya no está (`RN-6`):
 * la mención se pinta de forma neutra, sin enlace, en vez de fallar o de
 * llevar a una cuenta que no existe.
 */
final readonly class MentionView
{
    public function __construct(
        public ?string $userId,
        public ?string $name,
        public int $position,
    ) {
    }
}
