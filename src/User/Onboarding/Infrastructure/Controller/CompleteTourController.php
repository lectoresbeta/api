<?php

declare(strict_types=1);

namespace LectoresBeta\User\Onboarding\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\User\Onboarding\Application\Command\FinishTour;
use LectoresBeta\User\Onboarding\Application\Handler\FinishTourHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * `POST /api/v1/me/tour/completion` (`FEAT-USR-026`).
 *
 * **Cerrar el tour y terminarlo son la misma operación con distinto
 * `dismissed`**, y las dos cuentan como visto. Quien lo cierra en el primer
 * globo ha decidido que no le interesa, y volver a enseñárselo mañana sería
 * no haberle escuchado.
 *
 * Lo que la distinción guarda es la única métrica que dice si el tour
 * funciona: cuánta gente llega al final y cuánta lo cierra enseguida.
 *
 * **Funciona con la cuenta sin activar** (`RN-6`), como el onboarding: no es
 * publicar contenido, es decir que ya has visto algo.
 */
#[AsController]
final readonly class CompleteTourController
{
    public function __construct(
        private FinishTourHandler $finish,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $body = JsonBody::of($request);

        ($this->finish)(new FinishTour(
            CurrentUser::identifierFrom($this->security),
            $body->string('tourId'),
            $body->int('lastStep'),
            true === $body->bool('dismissed'),
        ));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
