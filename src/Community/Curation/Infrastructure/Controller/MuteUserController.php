<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Curation\Infrastructure\Controller;

use LectoresBeta\Community\Curation\Application\Command\MuteUser;
use LectoresBeta\Community\Curation\Application\Handler\MuteUserHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PUT /api/v1/users/{userId}/muted` (`FEAT-COM-033`).
 *
 * Quitar a alguien del muro **sin cortar nada más**: el seguimiento sigue,
 * puede comentarme, escribirme y mencionarme, y sus comentarios en hilos
 * ajenos se siguen viendo. Eso es bloquear, y es `FEAT-COM-034`.
 *
 * No se le avisa.
 */
#[AsController]
final readonly class MuteUserController
{
    public function __construct(
        private MuteUserHandler $mute,
        private Security $security,
    ) {
    }

    public function __invoke(string $userId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->mute)(new MuteUser($user->getUserIdentifier(), $userId));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
