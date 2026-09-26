<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderAccess\Application\Handler;

use LectoresBeta\Reading\BetaReaderAccess\Application\Command\RevokeBetaReaderAccess;
use LectoresBeta\Reading\BetaReaderAccess\Domain\Event\BetaReaderAccessRevoked;
use LectoresBeta\Reading\BetaReaderAccess\Domain\Exception\BetaReaderAccessNotFound;
use LectoresBeta\Reading\BetaReaderAccess\Domain\Repository\BetaReaderAccessRepository;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\Work\Manuscript\Application\Contract\WorkAccessBriefs;

/**
 * El autor retira un acceso (`FEAT-RDG-010`).
 *
 * Cierra `R-1`, abierta desde la primera ficha de este contexto: se entra a
 * una obra por tres caminos —dos de ellos abiertos por el propio autor— y
 * hasta ahora **no había forma de salir** salvo bloquear a la persona, que es
 * una respuesta social a un problema que muchas veces no lo es.
 *
 * **Idempotente** (`RN-2`): si esa persona no tiene acceso vivo, no pasa
 * nada. Quien revoca dos veces suele ser un reintento, y el estado que pedía
 * ya se cumple.
 *
 * Lo que esto se lleva por delante es lo mismo que un bloqueo (`RN-4`): una
 * corrección en curso deja de poder entregarse, y quien la estaba
 * escribiendo no cobra, porque nunca entregó. Su borrador se conserva. Lo ya
 * entregado no se toca (`RN-5`): el autor lo pagó y el lector lo ganó.
 *
 * Y lo que **no** consigue, que conviene no esperar: en una obra `PUBLIC`
 * esta persona vuelve a tener acceso en cuanto empiece otra corrección
 * (`RN-7`). Revocar corta lo que está pasando ahora; para dejar a alguien
 * fuera hay que cerrar la modalidad de la obra o bloquearle.
 */
final readonly class RevokeBetaReaderAccessHandler
{
    public function __construct(
        private BetaReaderAccessRepository $accesses,
        private WorkAccessBriefs $works,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(RevokeBetaReaderAccess $command): void
    {
        // La autoría se comprueba contra `Work`, que es de quien es el dato,
        // y no contra el acceso: así una obra que cambiase de manos no
        // dejaría revocando a quien ya no es su autor.
        $work = $this->works->ofWork($command->workId);

        if (null === $work || $work->authorId !== $command->authorId) {
            throw BetaReaderAccessNotFound::work();
        }

        try {
            $workId = WorkId::fromString($command->workId);
            $readerId = ReaderId::fromString($command->readerId);
        } catch (InvalidValue) {
            return;
        }

        $access = $this->accesses->liveFor($readerId, $workId);

        if (null === $access) {
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
