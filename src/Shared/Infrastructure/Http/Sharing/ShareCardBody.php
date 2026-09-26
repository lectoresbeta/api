<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Infrastructure\Http\Sharing;

use LectoresBeta\Shared\Application\Sharing\ShareCard;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * La forma en HTTP de una tarjeta para compartir (`FEAT-WRK-011`,
 * `FEAT-COM-020`).
 *
 * Una sola, compartida por los dos endpoints, porque lo que consume la
 * respuesta es lo mismo en los dos casos: el generador de etiquetas Open
 * Graph del cliente. Dos formas distintas para el mismo destino serían dos
 * plantillas que mantener.
 *
 * **Se cachea en público** porque no hay nada de nadie: solo responde por
 * contenido que ya era visible para cualquiera. Un rastreador que pregunte
 * dos veces en una hora recibe lo mismo.
 */
final readonly class ShareCardBody
{
    /**
     * Cinco minutos. Lo justo para absorber a los rastreadores que llegan
     * juntos cuando algo se comparte, sin que un cambio de título tarde una
     * tarde en verse.
     */
    private const CACHE_SECONDS = 300;

    public static function respond(ShareCard $card): Response
    {
        $response = new JsonResponse([
            'url' => $card->url,
            'title' => $card->title,
            'description' => $card->description,
            'imageUrl' => $card->imageUrl,
        ]);

        $response->setPublic();
        $response->setMaxAge(self::CACHE_SECONDS);

        return $response;
    }
}
