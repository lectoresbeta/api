<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Infrastructure\Controller;

use LectoresBeta\User\Profile\Application\Handler\GetMyProfileHandler;
use LectoresBeta\User\Profile\Application\Query\GetMyProfile;
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
 */
#[AsController]
final readonly class GetMyProfileController
{
    public function __construct(
        private GetMyProfileHandler $profile,
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
        ));
    }
}
