<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Ingest\Infrastructure\Controller;

use LectoresBeta\Work\Ingest\Application\Command\UploadManuscript;
use LectoresBeta\Work\Ingest\Application\Handler\UploadManuscriptHandler;
use LectoresBeta\Work\Ingest\Domain\Exception\DocumentNotReadable;
use LectoresBeta\Work\Ingest\Infrastructure\Document\PlainAndOfficeTextExtractor;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `POST /api/v1/manuscript-uploads` (`FEAT-WRK-002`).
 *
 * **Aquí no se crea ninguna obra.** Se lee el fichero, se propone un troceado
 * y se devuelve para que el autor lo revise; la obra nace al confirmar. Esa
 * es la respuesta a `W-4`: el servidor propone y el autor decide.
 *
 * Síncrono y no `202`. Leer un `.docx` de setenta y cinco mil palabras es
 * abrir un zip y recorrer un XML, no un trabajo de fondo, y el tope de diez
 * megas acota el peor caso. Montar una cola y un endpoint de estado para eso
 * sería complejidad sin nada al otro lado.
 */
#[AsController]
final readonly class UploadManuscriptController
{
    public function __construct(
        private UploadManuscriptHandler $upload,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $file = $request->files->get('file');

        if (!$file instanceof UploadedFile) {
            throw DocumentNotReadable::inThatFormat(PlainAndOfficeTextExtractor::ACCEPTED);
        }

        $proposal = ($this->upload)(new UploadManuscript(
            $user->getUserIdentifier(),
            (string) file_get_contents($file->getPathname()),
            // El nombre que traiga, **solo como pista**: quien decide el
            // formato es el contenido, y este nombre se sanea antes de
            // guardarse y no se usa nunca como ruta.
            $file->getClientOriginalName(),
        ));

        return new JsonResponse([
            'uploadId' => $proposal->uploadId,
            'filename' => $proposal->filename,
            'wordCount' => $proposal->wordCount,
            'chapters' => $proposal->chapters,
            'expiresAt' => $proposal->expiresAt->format(\DATE_ATOM),
        ], Response::HTTP_CREATED);
    }
}
