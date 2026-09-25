<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Recommendation\Infrastructure\Controller;

use LectoresBeta\Community\Recommendation\Application\DTO\AuthorSuggestion;
use LectoresBeta\Community\Recommendation\Application\Handler\ListAuthorSuggestionsHandler;
use LectoresBeta\Community\Recommendation\Application\Query\ListAuthorSuggestions;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/home/author-suggestions` (`FEAT-COM-018`).
 *
 * El bloque «todavía no sigues a ningún autor» del muro. Cierra el cabo
 * suelto del onboarding: el paso 3 es opcional y además **se omite solo**
 * cuando no hay autores suficientes, así que sin esto quien lo saltara se
 * quedaría con un muro pobre y sin una vía evidente de arreglarlo.
 *
 * **Es el mismo caso de uso que el paso 3** (`RN-3`): mismas tarjetas, mismo
 * criterio, misma cadena de relleno. Lo que cambia es dónde se pinta, cuántas
 * caben y una condición —solo a quien no sigue a nadie—, y por eso son dos
 * rutas y un solo motor: duplicar la regla sería tener dos sitios donde
 * cambiarla.
 */
#[AsController]
final readonly class ListHomeAuthorSuggestionsController
{
    /**
     * Cuatro, que es lo que pinta el diseño del bloque (`S-2`), frente a las
     * diez de la pantalla completa del onboarding. No es un criterio distinto:
     * es el hueco que hay.
     */
    private const HOW_MANY = 4;

    public function __construct(
        private ListAuthorSuggestionsHandler $suggestions,
        private Security $security,
    ) {
    }

    public function __invoke(): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $answer = ($this->suggestions)(new ListAuthorSuggestions(
            $user->getUserIdentifier(),
            onlyIfFollowingNobody: true,
            howMany: self::HOW_MANY,
        ));

        return new JsonResponse([
            'shouldDisplay' => $answer->shouldDisplay,
            'reason' => $answer->reason,
            'suggestions' => array_map(
                static fn (AuthorSuggestion $one): array => [
                    'userId' => $one->userId,
                    'displayName' => $one->displayName,
                    'avatarUrl' => $one->avatarUrl,
                    'followerCount' => $one->followerCount,
                    'publicationCount' => $one->publicationCount,
                    'matchedGenres' => $one->matchedGenres,
                    'following' => $one->following,
                ],
                $answer->suggestions,
            ),
        ]);
    }
}
