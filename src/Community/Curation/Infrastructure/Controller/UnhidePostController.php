<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Curation\Infrastructure\Controller;

use LectoresBeta\Community\Curation\Application\Command\UnhidePost;
use LectoresBeta\Community\Curation\Application\Handler\UnhidePostHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `DELETE /api/v1/posts/{postId}/hidden` (`FEAT-COM-022` `RN-3`).
 *
 * Volver a mostrarla. No hay lista de ocultas —sería una bandeja que
 * gestionar—, así que a esto se llega con el identificador que quien acaba de
 * ocultar todavía tiene: el «deshacer» del aviso que sale justo después.
 *
 * Funciona aunque la tarjeta ya no esté en el muro, que es precisamente el
 * caso.
 */
#[AsController]
final readonly class UnhidePostController
{
    public function __construct(
        private UnhidePostHandler $handler,
        private Security $security,
    ) {
    }

    public function __invoke(string $postId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->handler)(new UnhidePost($user->getUserIdentifier(), $postId));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
