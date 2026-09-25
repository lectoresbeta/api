<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\User\Preferences\Application\Command\UpdateContentPreferences;
use LectoresBeta\User\Preferences\Application\Handler\GetMyContentPreferencesHandler;
use LectoresBeta\User\Preferences\Application\Handler\UpdateContentPreferencesHandler;
use LectoresBeta\User\Preferences\Application\Query\GetMyContentPreferences;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET` y `PUT /api/v1/me/content-preferences` (`FEAT-USR-043`).
 *
 * El lector decide qué no quiere ver; el autor declaró qué hay
 * (`FEAT-WRK-017`). **El filtrado lo hace el servidor**: un filtro de cliente
 * significaría que el contenido viaja hasta el navegador de quien pidió no
 * verlo, y para material sensible eso no sirve de nada.
 *
 * Las dos operaciones comparten controlador porque comparten el recurso: lo
 * que cambia es el verbo.
 */
#[AsController]
final readonly class ContentPreferencesController
{
    public function __construct(
        private GetMyContentPreferencesHandler $mine,
        private UpdateContentPreferencesHandler $update,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        if ($request->isMethod('PUT')) {
            ($this->update)(new UpdateContentPreferences(
                $user->getUserIdentifier(),
                JsonBody::of($request)->stringList('excludedWarnings'),
            ));
        }

        return new JsonResponse([
            'excludedWarnings' => ($this->mine)(new GetMyContentPreferences($user->getUserIdentifier())),
        ]);
    }
}
