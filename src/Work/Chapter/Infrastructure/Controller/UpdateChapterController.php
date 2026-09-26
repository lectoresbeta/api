<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\Work\Chapter\Application\Command\UpdateChapter;
use LectoresBeta\Work\Chapter\Application\Handler\UpdateChapterHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PUT /api/v1/chapters/{chapterId}` (`FEAT-WRK-005`).
 *
 * `PUT` porque se sustituye el texto entero, que es lo que hace un editor al
 * guardar.
 *
 * Si alguien había empezado a corregir esta versión, el guardado **archiva
 * una copia** antes de pisarla: quien está escribiendo una corrección sigue
 * viendo el texto que empezó a leer, y la que ya entregó sigue hablando de
 * algo que existe.
 */
#[AsController]
final readonly class UpdateChapterController
{
    public function __construct(
        private UpdateChapterHandler $update,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $chapterId): Response
    {
        $author = $this->security->getUser();

        if (null === $author) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $body = JsonBody::of($request);

        ($this->update)(new UpdateChapter(
            $chapterId,
            $author->getUserIdentifier(),
            $body->string('title'),
            $body->has('title'),
            (string) $body->string('contentHtml'),
        ));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
