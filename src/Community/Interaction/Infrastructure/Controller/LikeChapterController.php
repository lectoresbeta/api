<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Infrastructure\Controller;

use LectoresBeta\Community\Interaction\Application\Command\LikeChapter;
use LectoresBeta\Community\Interaction\Application\Handler\LikeChapterHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PUT /api/v1/chapters/{chapterId}/like` (`FEAT-COM-036`).
 *
 * `PUT` y no `POST` porque **fija un estado**: el capítulo acaba apoyado por
 * quien llama, haya pulsado una vez o cinco.
 */
#[AsController]
final readonly class LikeChapterController
{
    public function __construct(
        private LikeChapterHandler $likes,
        private Security $security,
    ) {
    }

    public function __invoke(string $chapterId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        return new JsonResponse([
            'liked' => true,
            'likeCount' => $this->likes->like(new LikeChapter($user->getUserIdentifier(), $chapterId)),
        ]);
    }
}
