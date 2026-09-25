<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\Work\Chapter\Application\Command\SetChapterVisibility;
use LectoresBeta\Work\Chapter\Application\Handler\SetChapterVisibilityHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PUT /api/v1/chapters/{chapterId}/visibility` (`FEAT-WRK-008`).
 *
 * `PUT` porque fija un estado, como `changeWorkStatus` y `setAccessMode`.
 *
 * **La obra no tiene visibilidad**: tiene estado, y se cambia por su propia
 * operación. Esto es solo del capítulo.
 */
#[AsController]
final readonly class SetChapterVisibilityController
{
    public function __construct(
        private SetChapterVisibilityHandler $setVisibility,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $chapterId): Response
    {
        $author = $this->security->getUser();

        if (null === $author) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->setVisibility)(new SetChapterVisibility(
            $chapterId,
            $author->getUserIdentifier(),
            (string) JsonBody::of($request)->string('visibility'),
        ));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
