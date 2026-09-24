<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Infrastructure\Controller;

use LectoresBeta\User\Profile\Application\Handler\GetMyProfileHandler;
use LectoresBeta\User\Profile\Application\Query\GetMyProfile;
use LectoresBeta\User\Profile\Infrastructure\Composition\ProfileCounters;
use LectoresBeta\User\Profile\Infrastructure\Http\EditableProfileBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/me/profile` (`FEAT-USR-008`).
 *
 * Lo que la pantalla de «Configuración › Perfil» necesita para abrirse. No
 * lleva el correo ni la fecha de nacimiento: se cambian por su propio camino
 * o no se cambian, y mezclarlos haría de una pantalla de presentación una
 * pantalla de seguridad.
 *
 * Y lo que necesita la cabecera de «Mi perfil», que es la misma lectura con
 * **cuatro cifras más** (`FEAT-USR-028`). Un endpoint y no dos porque es el
 * mismo recurso visto en dos sitios; los contadores se ensamblan aquí, en la
 * frontera, a partir de los contratos de tres contextos.
 */
#[AsController]
final readonly class GetMyProfileController
{
    public function __construct(
        private GetMyProfileHandler $profile,
        private ProfileCounters $counters,
        private Security $security,
    ) {
    }

    public function __invoke(): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        return new JsonResponse(EditableProfileBody::of(
            ($this->profile)(new GetMyProfile($user->getUserIdentifier())),
            $this->counters->of($user->getUserIdentifier()),
        ));
    }
}
