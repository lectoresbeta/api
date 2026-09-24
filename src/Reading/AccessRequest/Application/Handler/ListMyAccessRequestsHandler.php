<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessRequest\Application\Handler;

use LectoresBeta\Reading\AccessRequest\Application\DTO\AccessRequestPage;
use LectoresBeta\Reading\AccessRequest\Application\Query\ListMyAccessRequests;
use LectoresBeta\Reading\AccessRequest\Application\Service\PageOfRequests;
use LectoresBeta\Reading\AccessRequest\Domain\Repository\AccessRequestRepository;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;

/**
 * Lo que he pedido y en qué ha quedado (`FEAT-RDG-002`).
 *
 * Por defecto enseña **lo pendiente**, que es lo que alguien abre esta
 * pantalla a mirar. El histórico está a un filtro de distancia.
 */
final readonly class ListMyAccessRequestsHandler
{
    public function __construct(
        private AccessRequestRepository $requests,
        private PageOfRequests $page,
    ) {
    }

    public function __invoke(ListMyAccessRequests $query): AccessRequestPage
    {
        $limit = $this->page->size($query->limit);

        return $this->page->of($this->requests->ofRequester(
            ReaderId::fromString($query->requesterId),
            $this->page->status($query->status),
            $this->page->after($query->cursor),
            $limit + 1,
        ), $limit);
    }
}
