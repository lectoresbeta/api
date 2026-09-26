<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessRequest\Application\Handler;

use LectoresBeta\Reading\AccessRequest\Application\DTO\AccessRequestPage;
use LectoresBeta\Reading\AccessRequest\Application\Query\ListWorkAccessRequests;
use LectoresBeta\Reading\AccessRequest\Application\Service\PageOfRequests;
use LectoresBeta\Reading\AccessRequest\Domain\Exception\AccessRequestNotFound;
use LectoresBeta\Reading\AccessRequest\Domain\Repository\AccessRequestRepository;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\AuthorId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\WorkId;
use LectoresBeta\Work\Manuscript\Application\Contract\WorkAccessBriefs;

/**
 * Quién quiere leer mi obra (`FEAT-RDG-003`).
 *
 * La bandeja del autor. Lleva **el mensaje de cada solicitante**, que es lo
 * único que convierte una lista de identificadores en una decisión que se
 * pueda tomar.
 *
 * La obra de otra persona responde igual que una inexistente: la autoría se
 * comprueba contra `Work`, que es de quien es el dato, y no contra la propia
 * solicitud — así una obra que cambió de manos no deja bandejas abiertas.
 */
final readonly class ListWorkAccessRequestsHandler
{
    public function __construct(
        private AccessRequestRepository $requests,
        private WorkAccessBriefs $works,
        private PageOfRequests $page,
    ) {
    }

    public function __invoke(ListWorkAccessRequests $query): AccessRequestPage
    {
        $work = $this->works->ofWork($query->workId);

        if (null === $work || $work->authorId !== $query->authorId) {
            throw AccessRequestNotFound::work();
        }

        $limit = $this->page->size($query->limit);

        return $this->page->of($this->requests->onWork(
            WorkId::fromString($query->workId),
            AuthorId::fromString($query->authorId),
            $this->page->status($query->status),
            $this->page->after($query->cursor),
            $limit + 1,
        ), $limit);
    }
}
