<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Curation\Infrastructure\Controller;

use LectoresBeta\Community\Curation\Application\Command\HidePost;
use LectoresBeta\Community\Curation\Application\Handler\HidePostHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PUT /api/v1/posts/{postId}/hidden` (`FEAT-COM-022`).
 *
 * «Esto no me interesa.» La tarjeta desaparece del muro de quien la oculta y
 * de nadie más: no borra nada, no denuncia nada y no avisa a su autor.
 */
#[AsController]
final readonly class HidePostController
{
    public function __construct(
        private HidePostHandler $handler,
        private Security $security,
    ) {
    }

    public function __invoke(string $postId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->handler)(new HidePost($user->getUserIdentifier(), $postId));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
