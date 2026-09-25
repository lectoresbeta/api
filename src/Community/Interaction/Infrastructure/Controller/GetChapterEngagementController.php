<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Infrastructure\Controller;

use LectoresBeta\Community\Interaction\Application\Handler\GetChapterEngagementHandler;
use LectoresBeta\Community\Interaction\Application\Query\GetChapterEngagement;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/chapters/{chapterId}/engagement` (`FEAT-COM-036`, `R-1`).
 *
 * Las cifras de la cabecera de la pantalla de lectura. **Endpoint aparte del
 * capítulo** y no dos campos más en `GET /chapters/{id}`: el capítulo lo
 * sirve `Work` y esto es de `Community`, y juntarlos obligaría a uno de los
 * dos contextos a conocer al otro.
 *
 * Trae apoyos y comentarios, **no lecturas**: qué cuenta como «lectura» sigue
 * sin definirse (`H-3`), y un contador que nadie sabe qué mide es peor que no
 * tenerlo.
 */
#[AsController]
final readonly class GetChapterEngagementController
{
    public function __construct(
        private GetChapterEngagementHandler $engagement,
        private Security $security,
    ) {
    }

    public function __invoke(string $chapterId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $view = ($this->engagement)(new GetChapterEngagement($user->getUserIdentifier(), $chapterId));

        return new JsonResponse([
            'chapterId' => $view->chapterId,
            'likeCount' => $view->likeCount,
            'commentCount' => $view->commentCount,
            'likedByViewer' => $view->likedByViewer,
        ]);
    }
}
