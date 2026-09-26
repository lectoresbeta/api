<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\Handler;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\Work\Manuscript\Application\Event\WorkRated;
use LectoresBeta\Work\Manuscript\Domain\Entity\WorkReaderRating;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkReaderRatingRepository;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * Lo que una valoración significa **dentro de `Work`** (`FEAT-WRK-015`).
 *
 * `Feedback` posee las valoraciones: quién puede dejarlas, en qué rango y qué
 * hace falta para ganarse el derecho. Aquí solo se mantiene el agregado que
 * «Mis relatos» necesita para ordenar por «más valorados», porque ordenar una
 * lista preguntando nota a nota a otro contexto sería un N+1 al otro lado de
 * la frontera.
 *
 * **La nota anterior no viaja en el hecho**, y sin ella una valoración
 * cambiada dejaría la suma contando las dos. De ahí la fila por persona: es
 * lo que permite restar, y de paso hace esto idempotente sin registro de
 * duplicados — reprocesar el mismo hecho escribe la misma nota y no mueve el
 * agregado.
 */
final readonly class TrackWorkRating
{
    public function __construct(
        private WorkRepository $works,
        private WorkReaderRatingRepository $ratings,
        private TransactionalSession $session,
    ) {
    }

    public function __invoke(WorkRated $event): void
    {
        try {
            $workId = WorkId::fromString($event->workId);
        } catch (InvalidValue) {
            // Un hecho con un identificador ilegible se descarta:
            // reintentarlo lo dejaría dando vueltas para siempre.
            return;
        }

        $work = $this->works->ofId($workId);

        if (null === $work) {
            return;
        }

        $known = $this->ratings->between($workId, $event->readerId);

        if (null === $known) {
            $work->applyRating($event->rating, null);

            $this->session->execute(function () use ($work, $workId, $event): void {
                $this->works->save($work);
                $this->ratings->save(new WorkReaderRating(
                    $workId,
                    $event->readerId,
                    $event->rating,
                    $event->occurredAt(),
                ));
            });

            return;
        }

        $previous = $known->changeTo($event->rating, $event->occurredAt());

        if (null === $previous) {
            return;
        }

        $work->applyRating($event->rating, $previous);

        $this->session->execute(function () use ($work, $known): void {
            $this->works->save($work);
            $this->ratings->save($known);
        });
    }
}
