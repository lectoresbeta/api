<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\Work\Chapter\Application\Command\AddChapter;
use LectoresBeta\Work\Chapter\Application\Handler\AddChapterHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `POST /api/v1/works/{workId}/chapters` (`FEAT-WRK-001`).
 *
 * El contenido llega como HTML y se **sanea al guardarlo**, así que lo que se
 * devuelve puede no ser exactamente lo que se envió. No es un error: es la
 * regla, y el cliente debe rehidratar el editor con lo guardado.
 */
#[AsController]
final readonly class AddChapterController
{
    public function __construct(
        private AddChapterHandler $add,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $workId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $body = JsonBody::of($request);

        $chapterId = ($this->add)(new AddChapter(
            $workId,
            $user->getUserIdentifier(),
            (string) $body->string('content'),
            $body->string('title'),
        ));

        return new JsonResponse(['chapterId' => $chapterId], Response::HTTP_CREATED);
    }
}
