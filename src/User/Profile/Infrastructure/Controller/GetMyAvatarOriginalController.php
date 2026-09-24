<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Infrastructure\Controller;

use LectoresBeta\User\Profile\Application\Handler\GetMyAvatarOriginalHandler;
use LectoresBeta\User\Profile\Application\Query\GetMyAvatarOriginal;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/me/profile/avatar/original` (`FEAT-USR-037`).
 *
 * La foto sin recortar, **solo para quien la subió**. Es lo que carga el
 * editor al pulsar «Editar», y por eso no viaja como una URL dentro del
 * perfil: la original puede enseñar mucho más que el encuadre que su dueño
 * eligió mostrar, y una URL acaba copiada donde la ve alguien más.
 */
#[AsController]
final readonly class GetMyAvatarOriginalController
{
    public function __construct(
        private GetMyAvatarOriginalHandler $original,
        private Security $security,
    ) {
    }

    public function __invoke(): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $file = ($this->original)(new GetMyAvatarOriginal($user->getUserIdentifier()));

        return new Response($file->contents, Response::HTTP_OK, [
            'Content-Type' => $file->contentType,
            // Privada, y de nadie más: una caché compartida que guardase esto
            // podría servírselo a otra persona.
            'Cache-Control' => 'private, max-age=0, no-store',
        ]);
    }
}
