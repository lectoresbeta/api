<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderAccess\Application\Handler;

use LectoresBeta\Reading\BetaReaderAccess\Application\Event\CorrectionDraftDiscarded;
use LectoresBeta\Reading\BetaReaderAccess\Domain\Event\BetaReaderAccessRevoked;
use LectoresBeta\Reading\BetaReaderAccess\Domain\Repository\BetaReaderAccessRepository;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Echarse atrás deshace lo que empezar había concedido (`FEAT-RDG-001`
 * `RN-5`).
 *
 * Quien pulsa «descartar» está diciendo que no va a hacerlo. Dejarle acceso
 * permanente a una obra inédita por haber abierto un panel una vez sería
 * regalar lectura a cambio de nada, y esta plataforma custodia obra sin
 * publicar.
 *
 * Solo se deshace lo que nació de aquí y nunca llegó a nada: una solicitud
 * que el autor aceptó, una invitación que envió, o el acceso de quien ya
 * entregó una corrección de esa obra, no se tocan.
 *
 * Queda un hueco, y es conocido: **abandonar sin descartar no revoca nada**.
 * Cerrar la pestaña no es un hecho, nadie lo publica, y la alternativa
 * —caducar accesos por inactividad— añade un reloj y un estado nuevo para un
 * caso que el autor podrá resolver él mismo cuando exista `FEAT-RDG-010`.
 */
final readonly class RevokeAccessOnDraftDiscarded
{
    public function __construct(
        private BetaReaderAccessRepository $accesses,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(CorrectionDraftDiscarded $event): void
    {
        $workId = WorkId::fromString($event->workId);
        $readerId = ReaderId::fromString($event->readerId);
        $access = $this->accesses->liveFor($readerId, $workId);

        if (null === $access || !$access->isUndoneByWalkingAway()) {
            return;
        }

        $now = $this->clock->now();
        $access->revoke($now);

        $this->session->execute(function () use ($access): void {
            $this->accesses->save($access);
        });

        $this->events->publish(new BetaReaderAccessRevoked(
            EventId::generate(),
            $access->id(),
            $workId,
            $readerId,
            $now,
        ));
    }
}
