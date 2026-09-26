<?php

declare(strict_types=1);

namespace LectoresBeta\Work\PublicLink\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Response;

/**
 * Lo que impide que obra inédita acabe en un buscador (`FEAT-WRK-010`
 * `RN-11`, `FEAT-FBK-008` `RN-5`).
 *
 * `X-Robots-Tag` y no una etiqueta `meta`: esto es una API que devuelve JSON,
 * y una etiqueta dentro del HTML solo la pondría el cliente. La cabecera la
 * ve el rastreador aunque nadie haya pintado nada.
 *
 * Va con `no-store` porque son dos formas del mismo cuidado: lo que no debe
 * indexarse tampoco debe quedarse guardado en un intermediario.
 */
final class NoIndex
{
    public static function on(Response $response): Response
    {
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet');
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
