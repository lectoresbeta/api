<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Infrastructure\Controller;

use LectoresBeta\Community\Interaction\Application\Command\UndoRepost;
use LectoresBeta\Community\Interaction\Application\Handler\UndoRepostHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `DELETE /api/v1/posts/{postId}/repost` (`FEAT-COM-019` `RN-9`).
 *
 * Existe además del botón que alterna porque dicen cosas distintas: aquél es
 * «cambia lo que haya», este es «quítalo». Deshacer lo ya deshecho no es un
 * error — el estado que se pedía ya se cumple.
 */
#[AsController]
final readonly class UndoRepostController
{
    public function __construct(
        private UndoRepostHandler $undo,
        private Security $security,
    ) {
    }

    public function __invoke(string $postId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->undo)(new UndoRepost($postId, $user->getUserIdentifier()));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
