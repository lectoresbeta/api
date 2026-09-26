<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Infrastructure\Controller;

use LectoresBeta\User\Profile\Application\Command\DeleteMyAvatar;
use LectoresBeta\User\Profile\Application\Handler\DeleteMyAvatarHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `DELETE /api/v1/me/profile/avatar` (`FEAT-USR-037`).
 *
 * Siempre `204`, tuviera foto o no: quitar una que no hay no es un fallo.
 */
#[AsController]
final readonly class DeleteAvatarController
{
    public function __construct(
        private DeleteMyAvatarHandler $delete,
        private Security $security,
    ) {
    }

    public function __invoke(): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->delete)(new DeleteMyAvatar($user->getUserIdentifier()));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
