<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\Work\Manuscript\Application\Command\SetContentRating;
use LectoresBeta\Work\Manuscript\Application\Handler\SetContentRatingHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PUT /api/v1/works/{workId}/content-rating` (`FEAT-WRK-017`).
 *
 * Un `PUT` con la clasificación entera, como el modo de acceso y las
 * temáticas: lo que el autor envía es **cómo queda declarada su obra**. Una
 * lista vacía de etiquetas es una declaración legítima —«no contiene nada de
 * esto»— y por eso `adultsOnly` no puede faltar: si las dos cosas pudieran
 * omitirse, un cuerpo vacío diría «apta para todos» sin que nadie lo hubiera
 * dicho.
 *
 * Devuelve lo que ha quedado guardado, no un `204`: quien acaba de declarar
 * algo de lo que responde quiere ver lo que se ha entendido.
 */
#[AsController]
final readonly class SetContentRatingController
{
    public function __construct(
        private SetContentRatingHandler $classify,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $workId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $body = JsonBody::of($request);

        $rating = ($this->classify)(new SetContentRating(
            $workId,
            $user->getUserIdentifier(),
            $body->bool('adultsOnly'),
            $body->stringList('contentWarnings'),
        ));

        return new JsonResponse([
            'adultsOnly' => $rating->adultsOnly,
            'contentWarnings' => $rating->contentWarnings,
        ]);
    }
}
