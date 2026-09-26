<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Infrastructure\Controller;

use LectoresBeta\User\Profile\Application\Command\DeleteMyCover;
use LectoresBeta\User\Profile\Application\Handler\DeleteMyCoverHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `DELETE /api/v1/me/profile/cover` (`FEAT-USR-016` `RN-6`).
 *
 * Devuelve la página al tema sin imagen y borra el fichero. Quitar un fondo
 * que no hay responde igual: el estado que se pedía ya se cumple.
 */
#[AsController]
final readonly class DeleteCoverController
{
    public function __construct(
        private DeleteMyCoverHandler $delete,
        private Security $security,
    ) {
    }

    public function __invoke(): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->delete)(new DeleteMyCover($user->getUserIdentifier()));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
