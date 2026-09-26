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
 * `DELETE /api/v1/chapters/{chapterId}/like` (`FEAT-COM-036`).
 *
 * Idempotente: retirar un apoyo que no está deja el mundo igual.
 *
 * **No exige el techo de audiencia del perfil.** Quien apoyó un capítulo y
 * después se encontró con que el autor cerró sus comentarios tiene que poder
 * deshacer lo suyo: un gesto que no se puede retirar no era un gesto.
 */
#[AsController]
final readonly class UnlikeChapterController
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
            'liked' => false,
            'likeCount' => $this->likes->unlike(new LikeChapter($user->getUserIdentifier(), $chapterId)),
        ]);
    }
}
