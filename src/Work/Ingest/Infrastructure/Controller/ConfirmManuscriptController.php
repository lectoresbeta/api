<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Ingest\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\Work\Ingest\Application\Command\ConfirmManuscript;
use LectoresBeta\Work\Ingest\Application\Handler\ConfirmManuscriptHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `POST /api/v1/manuscript-uploads/{uploadId}/work` (`FEAT-WRK-002`).
 *
 * La segunda mitad: el autor confirma el troceado y **aquí nace la obra**.
 * Lo que sale es exactamente lo que sale de `FEAT-WRK-001` —una obra con sus
 * capítulos, sus temáticas y sus hechos—, porque un manuscrito subido no es
 * una obra de segunda clase.
 *
 * Los títulos de capítulo se pueden corregir y nada más. Reordenar o partir
 * se hace después con `FEAT-WRK-003` y `FEAT-WRK-005`, que ya existen:
 * reimplementarlos dentro de la ingesta sería tener dos editores.
 */
#[AsController]
final readonly class ConfirmManuscriptController
{
    public function __construct(
        private ConfirmManuscriptHandler $confirm,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $uploadId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $body = JsonBody::of($request);

        return new JsonResponse([
            'workId' => ($this->confirm)(new ConfirmManuscript(
                $user->getUserIdentifier(),
                $uploadId,
                $body->string('title'),
                $body->string('synopsis'),
                $body->stringList('genres'),
                // Posicional: `["Prólogo", "", "Los guardianes"]`. Una cadena
                // vacía deja el título que se propuso, así que corregir el
                // primero de treinta no obliga a reenviar los treinta.
                $body->stringList('chapterTitles'),
            )),
        ], Response::HTTP_CREATED);
    }
}
