<?php

declare(strict_types=1);

namespace LectoresBeta\User\Legal\Infrastructure\Controller;

use LectoresBeta\User\Legal\Application\Handler\ListLegalDocumentsHandler;
use LectoresBeta\User\Legal\Application\Query\ListLegalDocuments;
use LectoresBeta\User\Legal\Domain\Entity\LegalDocument;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * `GET /api/v1/legal/documents` (`FEAT-USR-024`).
 *
 * **Sin sesión**, porque hace falta antes de tenerla: el formulario de alta
 * enseña la casilla con los enlaces y devuelve la versión exacta que se
 * acepta. Sin este endpoint, el cliente tendría que llevar esa versión
 * codificada, y una versión codificada en el cliente es una versión que se
 * queda vieja sin que nadie lo note.
 */
#[AsController]
final readonly class ListLegalDocumentsController
{
    public function __construct(private ListLegalDocumentsHandler $documents)
    {
    }

    public function __invoke(): Response
    {
        return new JsonResponse([
            'documents' => array_map(
                static fn (LegalDocument $document): array => [
                    'type' => $document->type()->value,
                    'version' => $document->version(),
                    'effectiveFrom' => $document->effectiveFrom()->format(\DATE_ATOM),
                    'url' => $document->url(),
                ],
                ($this->documents)(new ListLegalDocuments()),
            ),
        ]);
    }
}
