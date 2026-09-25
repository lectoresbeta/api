<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\Handler;

use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\Work\Chapter\Domain\Repository\ChapterRepository;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId;
use LectoresBeta\Work\Manuscript\Application\Event\ContentReviewFlagged;
use LectoresBeta\Work\Manuscript\Domain\Event\WorkBlockedByModeration;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository;

/**
 * Retirar un capítulo que el revisor automático ha marcado (`FEAT-MOD-011`
 * `RN-4`).
 *
 * Usa **el mismo bloqueo que una reclamación estimada** y no un estado nuevo,
 * y esa es la decisión de la implementación. El bloqueo de moderación ya
 * existe, ya está probado y ya tiene su salida —solo un moderador lo levanta—,
 * que es exactamente la garantía que hace falta aquí: **lo que esconde una
 * máquina lo devuelve una persona**.
 *
 * Inventar un `UNDER_REVIEW` en la máquina de estados de la obra habría hecho
 * lo mismo con una transición más, en un flujo que hoy funciona y que varios
 * contextos escuchan. Si `MOD-34` decide algún día que el autor espera al
 * veredicto en vez de publicar y retirar, ese será el momento de añadirlo,
 * con datos y no por precaución.
 *
 * Idempotente por lo mismo que su hermano: lo que decide si se anuncia algo
 * es **si el estado ha cambiado de verdad**. Un capítulo ya bloqueado no se
 * vuelve a bloquear ni se vuelve a contar.
 */
final readonly class WithdrawFlaggedChapter
{
    public function __construct(
        private ChapterRepository $chapters,
        private WorkRepository $works,
        private EventPublisher $events,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(ContentReviewFlagged $event): void
    {
        try {
            $chapter = $this->chapters->ofId(ChapterId::fromString($event->chapterId));
        } catch (InvalidValue) {
            return;
        }

        if (null === $chapter || $chapter->isBlocked()) {
            return;
        }

        $work = $this->works->ofId($chapter->workId());

        if (null === $work) {
            return;
        }

        $now = $this->clock->now();
        $chapter->block($now);

        $this->session->execute(fn () => $this->chapters->save($chapter));

        $this->events->publish(new WorkBlockedByModeration(
            EventId::generate(),
            $work->id()->value(),
            $work->authorId()->value(),
            $work->title()->value(),
            'CHAPTER',
            $chapter->id()->value(),
            // El motivo dice que fue automático, no qué encontró el revisor:
            // ese detalle está en la reclamación que lo acompaña, que es
            // donde un humano lo va a leer.
            'AUTOMATIC_CONTENT_REVIEW',
            null,
            $now,
        ));
    }
}
