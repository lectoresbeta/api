<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Application\Handler;

use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\Work\Chapter\Application\Command\SetChapterVisibility;
use LectoresBeta\Work\Chapter\Domain\Enum\ChapterVisibility;
use LectoresBeta\Work\Chapter\Domain\Event\ChapterVisibilityChanged;
use LectoresBeta\Work\Chapter\Domain\Exception\ChapterIsBlocked;
use LectoresBeta\Work\Chapter\Domain\Repository\ChapterRepository;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId;
use LectoresBeta\Work\Manuscript\Domain\Exception\WorkNotFound;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;

/**
 * Ocultar o volver a mostrar un capítulo (`FEAT-WRK-008`).
 *
 * **Ocultar, no borrar.** Las correcciones entregadas sobre ese capítulo se
 * siguen leyendo por las dos partes y el trabajo que alguien hizo sobre él
 * sigue contando: lo que cambia es que deja de poder leerse y de poder
 * corregirse.
 *
 * Un capítulo **bloqueado por moderación** no cambia de visibilidad por esta
 * vía (`RN-8`): el bloqueo lo levanta un moderador y nadie más, y dejar que
 * el autor lo «mostrara» sería deshacer una decisión que no es suya.
 *
 * Ocultar **no despublica la obra** aunque sea el último visible (`RN-9`): un
 * efecto colateral que cambia el estado de la obra es un efecto que el autor
 * no pidió.
 *
 * Lo que sí cambia es **el recuento de palabras de la obra** (`RN-6`): lo que
 * se enseña es lo que se puede leer, y con ello se mueve también el tiempo de
 * lectura, que se deriva de esa cifra. El número de capítulos **no** se toca:
 * un capítulo oculto sigue existiendo para su autor, que es quien lo ve en
 * «Mis relatos» y quien tiene que poder volver a mostrarlo.
 */
final readonly class SetChapterVisibilityHandler
{
    public function __construct(
        private WorkRepository $works,
        private ChapterRepository $chapters,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(SetChapterVisibility $command): void
    {
        $visibility = ChapterVisibility::tryFrom(strtoupper(trim($command->visibility)))
            ?? throw WorkNotFound::create();

        try {
            $chapter = $this->chapters->ofId(ChapterId::fromString($command->chapterId));
            $authorId = AuthorId::fromString($command->authorId);
        } catch (InvalidValue) {
            throw WorkNotFound::create();
        }

        if (null === $chapter) {
            throw WorkNotFound::create();
        }

        $work = $this->works->ofId($chapter->workId());

        if (null === $work || !$work->authorId()->equals($authorId)) {
            throw WorkNotFound::create();
        }

        if ($chapter->isBlocked()) {
            throw ChapterIsBlocked::create();
        }

        if ($visibility === $chapter->visibility()) {
            return;
        }

        $now = $this->clock->now();

        $this->session->execute(function () use ($work, $chapter, $visibility, $now): void {
            if (ChapterVisibility::HIDDEN === $visibility) {
                $chapter->hide($now);
            } else {
                $chapter->show($now);
            }

            $this->chapters->save($chapter);

            // `RN-6`: lo que se enseña es lo que se puede leer. El recuento
            // se ajusta por la diferencia y no se consulta entero, porque
            // dentro de la transacción la consulta todavía ve la visibilidad
            // anterior de este capítulo.
            $visible = $this->chapters->visibleWordCountOfWork($work->id());
            $words = ChapterVisibility::HIDDEN === $visibility
                ? $visible - $chapter->wordCount()
                : $visible + $chapter->wordCount();

            $work->recountContent(max(0, $words), $work->chapterCount(), $now);
            $this->works->save($work);
        });

        $this->events->publish(new ChapterVisibilityChanged(
            EventId::generate(),
            $chapter->id(),
            $work->id(),
            $work->authorId(),
            $visibility->value,
            $now,
        ));
    }
}
