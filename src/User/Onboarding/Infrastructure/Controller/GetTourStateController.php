<?php

declare(strict_types=1);

namespace LectoresBeta\User\Onboarding\Infrastructure\Controller;

use LectoresBeta\User\Onboarding\Application\Handler\GetTourStateHandler;
use LectoresBeta\User\Onboarding\Application\Query\GetTourState;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * `GET /api/v1/me/tour` (`FEAT-USR-026`).
 *
 * El estado también viaja en el contexto de sesión del layout
 * (`FEAT-USR-027`), para no añadir una petición más en cada carga. Este
 * endpoint existe igualmente porque el contexto de sesión se pide una vez y
 * el tour se puede consultar después.
 *
 * `tourId` es opcional y por defecto es el de la Home, que es el único que
 * hay. Está en la petición y no en la ruta para que **añadir un tour no sea
 * añadir dos endpoints**.
 */
#[AsController]
final readonly class GetTourStateController
{
    public function __construct(
        private GetTourStateHandler $tour,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $state = ($this->tour)(new GetTourState(
            CurrentUser::identifierFrom($this->security),
            $request->query->has('tourId') ? (string) $request->query->get('tourId') : null,
        ));

        return new JsonResponse([
            'tourId' => $state->tourId,
            'pending' => $state->pending,
            'lastStep' => $state->lastStep,
        ]);
    }
}
