<?php

declare(strict_types=1);

namespace LectoresBeta\User\Legal\Application\Handler;

use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\User\Legal\Application\Query\ListLegalDocuments;
use LectoresBeta\User\Legal\Domain\Entity\LegalDocument;
use LectoresBeta\User\Legal\Domain\Repository\LegalDocumentRepository;

/**
 * Qué documentos legales rigen ahora mismo (`FEAT-USR-024`).
 *
 * **Sin sesión**, porque hace falta antes de tenerla: el formulario de alta
 * tiene que enseñar la casilla con los enlaces y mandar de vuelta la versión
 * exacta que se acepta.
 *
 * Devuelve uno por tipo, el de mayor fecha de entrada en vigor **ya
 * cumplida**: publicar la política del mes que viene no cambia lo que se
 * acepta hoy.
 *
 * @see LegalDocument
 */
final readonly class ListLegalDocumentsHandler
{
    public function __construct(
        private LegalDocumentRepository $documents,
        private Clock $clock,
    ) {
    }

    /**
     * @return list<LegalDocument>
     */
    public function __invoke(ListLegalDocuments $query): array
    {
        return $this->documents->allInForce($this->clock->now());
    }
}
