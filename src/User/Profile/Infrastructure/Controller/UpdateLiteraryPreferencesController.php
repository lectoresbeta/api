<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\User\Profile\Application\Command\UpdateMyLiteraryPreferences;
use LectoresBeta\User\Profile\Application\Handler\UpdateMyLiteraryPreferencesHandler;
use LectoresBeta\User\Profile\Infrastructure\Http\GenreListBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PUT /api/v1/me/literary-preferences` (`FEAT-USR-009`).
 *
 * `PUT` y no `PATCH`: el cuerpo es la selección entera y sustituye a la
 * anterior. Es lo contrario que el perfil, y por una razón — allí los campos
 * son independientes y omitir uno significa «déjalo como está»; aquí el dato
 * **es** la lista, y una lista parcial no quiere decir nada.
 *
 * Devuelve cómo ha quedado, con los nombres, para que la pantalla repinte sin
 * volver a preguntar.
 */
#[AsController]
final readonly class UpdateLiteraryPreferencesController
{
    public function __construct(
        private UpdateMyLiteraryPreferencesHandler $update,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        return new JsonResponse(GenreListBody::of(($this->update)(new UpdateMyLiteraryPreferences(
            $user->getUserIdentifier(),
            JsonBody::of($request)->stringList('genres'),
        ))));
    }
}
