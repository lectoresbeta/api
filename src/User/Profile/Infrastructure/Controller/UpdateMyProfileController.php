<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\User\Profile\Application\Command\UpdateMyProfile;
use LectoresBeta\User\Profile\Application\Handler\UpdateMyProfileHandler;
use LectoresBeta\User\Profile\Infrastructure\Composition\ProfileCounters;
use LectoresBeta\User\Profile\Infrastructure\Http\EditableProfileBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PATCH /api/v1/me/profile` (`FEAT-USR-008`).
 *
 * `PATCH` y no `PUT` porque el mismo recurso se edita **campo a campo** desde
 * el perfil y **en bloque** desde Configuración. Lo que no se envía se queda
 * como estaba; enviar `description: null` la borra. Son cosas distintas y el
 * cuerpo las distingue por la presencia de la clave, no por su valor.
 *
 * El nombre de usuario y la foto **no viajan aquí**: cada uno tiene su
 * endpoint, y no por purismo — mezclados, una biografía se quedaría sin
 * guardar porque el nombre de usuario está ocupado o porque falló una subida.
 */
#[AsController]
final readonly class UpdateMyProfileController
{
    public function __construct(
        private UpdateMyProfileHandler $update,
        private ProfileCounters $counters,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $body = JsonBody::of($request);

        // La misma forma que al leer, contadores incluidos: quien acaba de
        // guardar recibe el recurso entero y no una versión recortada que le
        // obligue a recargar para volver a tener lo que ya tenía.
        return new JsonResponse(EditableProfileBody::of(
            ($this->update)(new UpdateMyProfile(
                $user->getUserIdentifier(),
                $body->has('name'),
                $body->string('name'),
                $body->has('description'),
                $body->string('description'),
            )),
            $this->counters->of($user->getUserIdentifier()),
        ));
    }
}
