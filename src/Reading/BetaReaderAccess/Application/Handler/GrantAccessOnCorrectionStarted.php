<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderAccess\Application\Handler;

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
 * `Feedback` no le habría dejado empezar— y encontrarlo vivo detiene el
 * proceso. En los dos casos se acierta sin preguntarle nada a `Work`, lo que
 * ahorra mantener aquí una copia de la modalidad de cada obra.
 *
 * La idempotencia sale del propio modelo, sin registro de hechos procesados:
 * un acceso vivo por par (lector, obra), y una reentrega lo encuentra.
 */
final readonly class GrantAccessOnCorrectionStarted
{
    public function __construct(
        private BetaReaderAccessRepository $accesses,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(CorrectionStarted $event): void
    {
        $workId = WorkId::fromString($event->workId);
        $readerId = ReaderId::fromString($event->readerId);

        if (null !== $this->accesses->liveFor($readerId, $workId)) {
            return;
        }

        $now = $this->clock->now();
        $access = new BetaReaderAccess(
            BetaReaderAccessId::generate(),
            $workId,
            $readerId,
            AuthorId::fromString($event->authorId),
            AccessSource::PUBLIC_JOIN,
            $now,
        );

        $this->session->execute(function () use ($access): void {
            $this->accesses->save($access);
        });

        $this->events->publish(new BetaReaderAccessGranted(
            EventId::generate(),
            $access->id(),
            $workId,
            AuthorId::fromString($event->authorId),
            $readerId,
            AccessSource::PUBLIC_JOIN->value,
            $now,
        ));
    }
}
