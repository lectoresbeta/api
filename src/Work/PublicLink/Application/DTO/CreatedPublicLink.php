<?php

declare(strict_types=1);

namespace LectoresBeta\Work\PublicLink\Application\DTO;

/**
 * Lo único que lleva el token, y una sola vez (`FEAT-WRK-010` `RN-2`).
 *
 * Se devuelve el token y no la URL entera porque el dominio de la aplicación
 * es cosa del cliente y de la configuración de despliegue, no de una capa que
 * no debería saber por qué host se la llama.
 */
final readonly class CreatedPublicLink
{
    public function __construct(
        public PublicLinkView $link,
        public string $token,
    ) {
    }
}
