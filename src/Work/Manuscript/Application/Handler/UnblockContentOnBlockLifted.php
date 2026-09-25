<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\Handler;

use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\Work\Chapter\Domain\Repository\ChapterRepository;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId;
use LectoresBeta\Work\Manuscript\Application\Event\ModerationBlockLifted;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * Un moderador ha levantado el bloqueo, así que el contenido vuelve
 * (`FEAT-MOD-003` `RN-7`).
 *
 * **Quien ejecuta es `Work`**, igual que al bloquear: el estado de una obra
 * es su modelo. `Moderation` dice qué se ha decidido y nada más.
 *
 * El umbral de los tres capítulos **no se recalcula al alza aquí** (`RN-10`):
 * desbloquear un capítulo no desbloquea la obra, y si la obra sigue
 * bloqueada, sigue bloqueada hasta que alguien levante ese bloqueo también.
 */
final readonly class UnblockContentOnBlockLifted
{
    private const WORK = 'WORK';
    private const CHAPTER = 'CHAPTER';

    public function __construct(
        private WorkRepository $works,
        private ChapterRepository $chapters,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(ModerationBlockLifted $event): void
    {
        $now = $this->clock->now();

        if (self::WORK === $event->targetType) {
            $work = $this->workOf($event->targetId);

            if (null === $work || !$work->isBlocked()) {
                return;
            }

            $work->unblock($now);
            $this->session->execute(fn () => $this->works->save($work));

            return;
        }

        if (self::CHAPTER !== $event->targetType) {
            return;
        }

        $chapter = $this->chapterOf($event->targetId);

        if (null === $chapter || !$chapter->isBlocked()) {
            return;
        }

        $chapter->unblock($now);
        $this->session->execute(fn () => $this->chapters->save($chapter));
    }

    private function workOf(string $id): ?\LectoresBeta\Work\Manuscript\Domain\Entity\Work
    {
        try {
            return $this->works->ofId(WorkId::fromString($id));
        } catch (InvalidValue) {
            return null;
        }
    }

    private function chapterOf(string $id): ?\LectoresBeta\Work\Chapter\Domain\Entity\Chapter
    {
        try {
            return $this->chapters->ofId(ChapterId::fromString($id));
        } catch (InvalidValue) {
            return null;
        }
    }
}
