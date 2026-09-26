<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Application\Handler;

use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\Work\Chapter\Application\Command\RemoveChapter;
use LectoresBeta\Work\Chapter\Domain\Entity\Chapter;
use LectoresBeta\Work\Chapter\Domain\Event\ChapterRemoved;
use LectoresBeta\Work\Chapter\Domain\Exception\ChapterHasCorrections;
use LectoresBeta\Work\Chapter\Domain\Exception\ChapterIsBlocked;
use LectoresBeta\Work\Chapter\Domain\Exception\WorkNeedsAChapter;
use LectoresBeta\Work\Chapter\Domain\Repository\ChapterRepository;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId;
use LectoresBeta\Work\Manuscript\Domain\Exception\WorkNotFound;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;

/**
 * Eliminar un capítulo (`FEAT-WRK-003`).
 *
 * Tres puertas, y las tres protegen algo que no es del autor:
 *
 * - **un capítulo que alguien corrigió no se borra** (`RN-6`): destruiría el
 *   trabajo de esa persona y el rastro de un cobro. Se oculta, que es lo
 *   mismo de cara al lector;
 * - **uno bloqueado por moderación, tampoco** (`RN-7`): sería la vía para
 *   hacer desaparecer contenido reclamado;
 * - **una obra publicada no se queda sin capítulos** (`RN-8`). En borrador
 *   sí, que ahí no hay nadie leyendo.
 *
 * Que alguien lo haya corregido se responde **sin salir del contexto**: la
 * versión del capítulo solo sube al archivar una que alguien leyó.
 */
final readonly class RemoveChapterHandler
{
    public function __construct(
        private WorkRepository $works,
        private ChapterRepository $chapters,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(RemoveChapter $command): void
    {
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

        if ($chapter->hasBeenCorrected()) {
            throw ChapterHasCorrections::create();
        }

        $remaining = array_values(array_filter(
            $this->chapters->ofWork($work->id()),
            static fn (Chapter $other): bool => !$other->id()->equals($chapter->id()),
        ));

        if ([] === $remaining && $work->status()->isReadableByOthers()) {
            throw WorkNeedsAChapter::create();
        }

        $now = $this->clock->now();
        $position = 0;
        $words = 0;

        foreach ($remaining as $other) {
            ++$position;

            // Los ocultos conservan su posición y no suman palabras
            // (`FEAT-WRK-008` `RN-6`).
            if (!$other->isHidden()) {
                $words += $other->wordCount();
            }

            if ($other->position() !== $position) {
                $other->moveTo($position, $now);
            }
        }

        $this->session->execute(function () use ($chapter, $remaining, $work, $words, $now): void {
            $this->chapters->remove($chapter);

            foreach ($remaining as $other) {
                $this->chapters->save($other);
            }

            $work->recountContent($words, \count($remaining), $now);
            $this->works->save($work);
        });

        $this->events->publish(new ChapterRemoved(
            EventId::generate(),
            $chapter->id(),
            $work->id(),
            $work->authorId(),
            array_map(static fn (Chapter $other): string => $other->id()->value(), $remaining),
            $now,
        ));
    }
}
