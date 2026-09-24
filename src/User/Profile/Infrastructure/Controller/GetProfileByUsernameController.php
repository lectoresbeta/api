<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Infrastructure\Controller;

use LectoresBeta\User\Profile\Application\Handler\GetProfileByUsernameHandler;
use LectoresBeta\User\Profile\Application\Query\GetProfileByUsername;
use LectoresBeta\User\Profile\Infrastructure\Http\PublicProfileBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * `GET /api/v1/profiles/{username}` (`FEAT-USR-035`).
 *
 * Resuelve el nombre y, si ya no lo usa nadie, el **alias vigente** que
 * alguien dejó atrás al cambiarse de nombre. Es lo que mantiene vivos los
 * enlaces compartidos.
 *
 * **No redirige.** Sería natural responder `301` a la URL canónica y no se
 * hace, porque este endpoint sirve datos y no páginas: quien construye la
 * barra de direcciones es el cliente. La respuesta lleva `canonicalUsername`
 * y `resolvedVia`, y con eso el frontend sustituye la URL sin recargar.
 */
#[AsController]
final readonly class GetProfileByUsernameController
{
    public function __construct(
        private GetProfileByUsernameHandler $profile,
        private Security $security,
    ) {
    }

    public function __invoke(string $username): Response
    {
        return new JsonResponse(PublicProfileBody::of(($this->profile)(new GetProfileByUsername(
            $username,
            $this->security->getUser()?->getUserIdentifier(),
        ))));
    }
}
