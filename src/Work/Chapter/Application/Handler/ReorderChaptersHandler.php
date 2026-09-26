<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Application\Handler;

use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\Work\Chapter\Application\Command\ReorderChapters;
use LectoresBeta\Work\Chapter\Domain\Event\ChaptersReordered;
use LectoresBeta\Work\Chapter\Domain\Exception\ChapterOrderIncomplete;
use LectoresBeta\Work\Chapter\Domain\Repository\ChapterRepository;
use LectoresBeta\Work\Manuscript\Domain\Exception\WorkNotFound;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * Reordenar los capítulos de una obra (`FEAT-WRK-003`).
 *
 * Recibe **la lista entera** en el orden deseado, no «mueve este de la 3 a la
 * 7». La lista completa es idempotente y sobrevive a que dos pestañas hagan
 * lo mismo a la vez; un movimiento relativo, no.
 *
 * Y se rechaza **entera** si no cuadra (`RN-2`): aplicar la mitad de un
 * reordenamiento deja la obra en un estado que el autor no pidió y no sabe
 * leer.
 *
 * Parece cosmético y no lo es. **El último capítulo es el que responde las
 * preguntas de alcance `LAST_CHAPTER`**, así que esto cambia lo que se pide
 * —y lo que se paga— en dos capítulos. Por eso el hecho sale con el orden
 * nuevo entero.
 */
final readonly class ReorderChaptersHandler
{
    public function __construct(
        private WorkRepository $works,
        private ChapterRepository $chapters,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(ReorderChapters $command): void
    {
        try {
            $work = $this->works->ofId(WorkId::fromString($command->workId));
            $authorId = AuthorId::fromString($command->authorId);
        } catch (InvalidValue) {
            throw WorkNotFound::create();
        }

        if (null === $work || !$work->authorId()->equals($authorId)) {
            throw WorkNotFound::create();
        }

        $chapters = [];

        foreach ($this->chapters->ofWork($work->id()) as $chapter) {
            $chapters[$chapter->id()->value()] = $chapter;
        }

        $wanted = array_values(array_unique($command->chapterIds));

        // Ni de más, ni de menos, ni de otra obra. Las tres cosas se
        // comprueban con una sola igualdad de conjuntos.
        if (\count($wanted) !== \count($chapters) || [] !== array_diff($wanted, array_keys($chapters))) {
            throw ChapterOrderIncomplete::create();
        }

        $now = $this->clock->now();
        $position = 0;
        $moved = [];

        foreach ($wanted as $chapterId) {
            ++$position;
            $chapter = $chapters[$chapterId];

            if ($chapter->position() === $position) {
                continue;
            }

            $chapter->moveTo($position, $now);
            $moved[] = $chapter;
        }

        if ([] === $moved) {
            return;
        }

        $this->session->execute(function () use ($moved): void {
            foreach ($moved as $chapter) {
                $this->chapters->save($chapter);
            }
        });

        $this->events->publish(new ChaptersReordered(
            EventId::generate(),
            $work->id(),
            $work->authorId(),
            $wanted,
            $now,
        ));
    }
}
