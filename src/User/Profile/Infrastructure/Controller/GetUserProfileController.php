<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Infrastructure\Controller;

use LectoresBeta\User\Profile\Application\Handler\GetProfileByUserIdHandler;
use LectoresBeta\User\Profile\Application\Query\GetProfileByUserId;
use LectoresBeta\User\Profile\Infrastructure\Http\PublicProfileBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * `GET /api/v1/users/{userId}` (`FEAT-USR-014`).
 *
 * **Público**: el perfil de quien no lo ha restringido es una URL que se
 * comparte, y exigir sesión para abrirla haría inútil compartirla. Quien sí
 * lo ha restringido no aparece, con sesión o sin ella.
 *
 * La sesión se lee si la hay, y solo sirve para una cosa: que su titular se
 * vea siempre a sí mismo. Esconderle su propio perfil sería absurdo, y es lo
 * que permite deshacer el ajuste — quien lo cierra sigue entrando a abrirlo.
 */
#[AsController]
final readonly class GetUserProfileController
{
    public function __construct(
        private GetProfileByUserIdHandler $profile,
        private Security $security,
    ) {
    }

    public function __invoke(string $userId): Response
    {
        return new JsonResponse(PublicProfileBody::of(($this->profile)(new GetProfileByUserId(
            $userId,
            $this->security->getUser()?->getUserIdentifier(),
        ))));
    }
}
