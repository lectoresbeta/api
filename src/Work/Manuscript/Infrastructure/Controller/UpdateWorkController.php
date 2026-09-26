<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\Work\Manuscript\Application\Command\UpdateWork;
use LectoresBeta\Work\Manuscript\Application\Handler\UpdateWorkHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PATCH /api/v1/works/{workId}` (`FEAT-WRK-005`).
 *
 * `PATCH` porque se tocan campos sueltos. La diferencia entre **no enviar**
 * `synopsis` y enviarla a `null` es real y se respeta: lo primero no la toca,
 * lo segundo la borra.
 *
 * Géneros y clasificación de contenido tienen sus propias operaciones y no se
 * duplican aquí.
 */
#[AsController]
final readonly class UpdateWorkController
{
    public function __construct(
        private UpdateWorkHandler $update,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $workId): Response
    {
        $author = $this->security->getUser();

        if (null === $author) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $body = JsonBody::of($request);

        ($this->update)(new UpdateWork(
            $workId,
            $author->getUserIdentifier(),
            $body->string('title'),
            $body->string('synopsis'),
            $body->has('synopsis'),
        ));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
