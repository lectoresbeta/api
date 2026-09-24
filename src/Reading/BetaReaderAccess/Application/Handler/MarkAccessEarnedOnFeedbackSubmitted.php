<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderAccess\Application\Handler;

use LectoresBeta\Reading\BetaReaderAccess\Application\Event\FeedbackSubmitted;
use LectoresBeta\Reading\BetaReaderAccess\Domain\Repository\BetaReaderAccessRepository;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Entregar una corrección **gana** el acceso (`FEAT-RDG-001` `RN-5`).
 *
 * A partir de aquí, descartar el borrador de otro capítulo ya no se lo quita:
 * hizo el trabajo una vez, y eso es lo que el acceso registra.
 *
 * De todo lo que `FeedbackSubmitted` significa, este contexto se queda con
 * dos campos. No sabe cuánto valía la corrección ni quiere saberlo.
 *
 * Idempotente sin llevar cuenta de nada: marcar dos veces lo que ya está
 * marcado no cambia nada, y por eso no hace falta un registro de hechos
 * procesados.
 */
final readonly class MarkAccessEarnedOnFeedbackSubmitted
{
    public function __construct(
        private BetaReaderAccessRepository $accesses,
        private TransactionalSession $session,
    ) {
    }

    public function __invoke(FeedbackSubmitted $event): void
    {
        $access = $this->accesses->liveFor(
            ReaderId::fromString($event->readerId),
            WorkId::fromString($event->workId),
        );

        if (null === $access || $access->isEarned()) {
            return;
        }

        $access->markEarned();

        $this->session->execute(function () use ($access): void {
            $this->accesses->save($access);
        });
    }
}
