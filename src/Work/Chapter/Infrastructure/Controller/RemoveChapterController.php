<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Infrastructure\Controller;

use LectoresBeta\Work\Chapter\Application\Command\RemoveChapter;
use LectoresBeta\Work\Chapter\Application\Handler\RemoveChapterHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `DELETE /api/v1/chapters/{chapterId}` (`FEAT-WRK-003`).
 *
 * Solo capítulos que **nadie ha corregido**: los demás se ocultan, porque
 * borrarlos destruiría el trabajo de quien los corrigió y el rastro de un
 * cobro.
 */
#[AsController]
final readonly class RemoveChapterController
{
    public function __construct(
        private RemoveChapterHandler $remove,
        private Security $security,
    ) {
    }

    public function __invoke(string $chapterId): Response
    {
        $author = $this->security->getUser();

        if (null === $author) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->remove)(new RemoveChapter($chapterId, $author->getUserIdentifier()));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
