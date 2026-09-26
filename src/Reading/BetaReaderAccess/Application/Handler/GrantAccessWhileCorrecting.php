<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderAccess\Application\Handler;

use LectoresBeta\Reading\BetaReaderAccess\Application\Event\CorrectionResumed;
use LectoresBeta\Reading\BetaReaderAccess\Application\Event\CorrectionStarted;
use LectoresBeta\Reading\BetaReaderAccess\Domain\Entity\BetaReaderAccess;
use LectoresBeta\Reading\BetaReaderAccess\Domain\Enum\AccessSource;
use LectoresBeta\Reading\BetaReaderAccess\Domain\Event\BetaReaderAccessGranted;
use LectoresBeta\Reading\BetaReaderAccess\Domain\Repository\BetaReaderAccessRepository;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\AuthorId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\BetaReaderAccessId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Ponerse a corregir convierte en lector beta (`FEAT-RDG-001`).
 *
 * **No hay pantalla.** Esta funcionalidad es el efecto aquí de un hecho que
 * ocurre en `Feedback`, y esa es toda su implementación: nadie pide ser
 * lector beta, se es por haberse puesto a trabajar.
 *
 * Se concede **al empezar y no al entregar** (`RN-4`), y lo decide un caso
 * concreto: quien está a medias cuando el autor restringe la obra. Con la
 * otra opción perdería el texto y no podría entregar, y su trabajo se
 * evaporaría en silencio. Este producto se sostiene sobre que eso no pase.
 *
 * **No se comprueba la modalidad de la obra** (`RN-7`), y no hace falta: si
 * era `PUBLIC`, corresponde conceder; si no, el lector ya tenía acceso —
 * `Feedback` no le habría dejado empezar ni reanudar— y encontrarlo vivo
 * detiene el proceso. En los dos casos se acierta sin preguntarle nada a
 * `Work`, lo que ahorra mantener aquí una copia de la modalidad de cada obra.
 *
 * **Empezar y reanudar hacen lo mismo aquí**, y son dos hechos porque en
 * `Credits` no lo son: reanudar no toma hueco ni fija precio. Los dos
 * significan «esta persona está corrigiendo esto ahora», que es lo único que
 * este contexto necesita saber.
 *
 * ## Por qué hacen falta dos comprobaciones
 *
 * Un acceso vivo por par (lector, obra) hace idempotente el caso normal: una
 * reentrega lo encuentra y para. **Pero no basta** (`R-22`). Si entre la
 * primera entrega y una reentrega alguien revocó el acceso —el autor, o un
 * bloqueo—, ya no hay nada vivo que encontrar, y sin la segunda comprobación
 * una persona expulsada recuperaría el acceso porque la cola repitió un
 * mensaje: en silencio, y sin que nadie lo decidiera.
 *
 * Por eso cada acceso guarda **qué hecho lo abrió**, y un hecho abre uno como
 * mucho. Preguntar por el hecho responde lo mismo esté el acceso vivo o
 * retirado, que es justo lo que una reentrega necesita.
 *
 * Y es lo que distingue este caso de su opuesto: volver a corregir después de
 * una revocación **sí** concede acceso de nuevo en una obra `PUBLIC`
 * (`FEAT-RDG-010` `RN-7`), porque eso trae un hecho distinto.
 */
final readonly class GrantAccessWhileCorrecting
{
    public function __construct(
        private BetaReaderAccessRepository $accesses,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function started(CorrectionStarted $event): void
    {
        $this->grant($event->workId, $event->authorId, $event->readerId, $event->eventId());
    }

    /**
     * Reanudar concede igual que empezar, y es la mitad que faltaba: quien
     * perdió el acceso a una obra `PUBLIC` conservando su borrador no tenía
     * por dónde volver. Reanudar no publicaba nada, así que su acceso solo
     * regresaba si la cola repetía un mensaje antiguo — por accidente, y
     * desde que un hecho abre un acceso como mucho, ni siquiera eso.
     */
    public function resumed(CorrectionResumed $event): void
    {
        $this->grant($event->workId, $event->authorId, $event->readerId, $event->eventId());
    }

    private function grant(string $work, string $author, string $reader, string $eventId): void
    {
        $workId = WorkId::fromString($work);
        $readerId = ReaderId::fromString($reader);

        if (null !== $this->accesses->liveFor($readerId, $workId)) {
            return;
        }

        // Este hecho ya abrió un acceso alguna vez. Que después se lo
        // retiraran no es asunto de una reentrega (`R-22`).
        if (null !== $this->accesses->grantedByEvent($eventId)) {
            return;
        }

        $now = $this->clock->now();
        $access = new BetaReaderAccess(
            BetaReaderAccessId::generate(),
            $workId,
            $readerId,
            AuthorId::fromString($author),
            AccessSource::PUBLIC_JOIN,
            $now,
            $eventId,
        );

        $this->session->execute(function () use ($access): void {
            $this->accesses->save($access);
        });

        $this->events->publish(new BetaReaderAccessGranted(
            EventId::generate(),
            $access->id(),
            $workId,
            AuthorId::fromString($author),
            $readerId,
            AccessSource::PUBLIC_JOIN->value,
            $now,
        ));
    }
}
