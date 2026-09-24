<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Catalogue\Application\Handler;

use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\Work\Catalogue\Application\Event\FeedbackSubmitted;
use LectoresBeta\Work\Catalogue\Domain\Entity\DeliveredCorrection;
use LectoresBeta\Work\Catalogue\Domain\Repository\CatalogueSignalRepository;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * Una corrección recibida **baja** la obra en el catálogo (`decision:0008`).
 *
 * Es la mitad de la propiedad que hace que el reparto se sostenga solo:
 * aparecer arriba trae correcciones, y cada corrección gasta saldo del autor
 * —lo cuenta `Credits`— y sube este contador. Los dos factores bajan a la vez
 * y la obra deja sitio a otra.
 *
 * La idempotencia la da la clave primaria: una fila por corrección, no un
 * contador que haya que defender de la reentrega.
 */
final readonly class CountDeliveredCorrection
{
    public function __construct(
        private CatalogueSignalRepository $signals,
        private TransactionalSession $session,
    ) {
    }

    public function __invoke(FeedbackSubmitted $event): void
    {
        $this->session->execute(function () use ($event): void {
            $this->signals->countDelivered(new DeliveredCorrection(
                $event->correctionId,
                WorkId::fromString($event->workId),
                $event->occurredAt(),
            ));
        });
    }
}
