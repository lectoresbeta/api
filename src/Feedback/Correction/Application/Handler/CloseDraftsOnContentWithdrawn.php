<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Handler;

use LectoresBeta\Feedback\Correction\Application\Event\ChapterVisibilityChanged;
use LectoresBeta\Feedback\Correction\Application\Event\WorkArchived;
use LectoresBeta\Feedback\Correction\Application\Event\WorkBlockedByModeration;
use LectoresBeta\Feedback\Correction\Domain\Event\CorrectionClosed;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionRepository;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\ChapterId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Avisar a quien tenía trabajo a medias sobre algo que acaba de cerrarse.
 *
 * Tres causas —la obra se bloquea ([`FEAT-MOD-003`](../../../../../docs/features/moderation/FEAT-MOD-003-block-work.md)
 * `RN-4`), el autor la retira ([`FEAT-WRK-006`](../../../../../docs/features/work/FEAT-WRK-006-delete-work.md)
 * `RN-4`) o le oculta un capítulo ([`FEAT-WRK-008`](../../../../../docs/features/work/FEAT-WRK-008-work-and-chapter-visibility.md)
 * `RN-5`)— y un solo trabajo, porque para quien estaba escribiendo son la
 * misma cosa: **su corrección ya no se puede entregar**.
 *
 * Hasta ahora lo descubría al intentarlo. Que pierda ese trabajo es
 * inevitable; que se entere por un error, no.
 *
 * **El borrador no se borra.** Es texto suyo, y el día que el contenido
 * vuelva sigue ahí. Lo único que sale de aquí es el aviso.
 */
final readonly class CloseDraftsOnContentWithdrawn
{
    public function __construct(
        private CorrectionRepository $corrections,
        private EventPublisher $events,
        private TransactionalSession $session,
    ) {
    }

    public function blocked(WorkBlockedByModeration $event): void
    {
        $this->close(
            $event->workId,
            // Un capítulo bloqueado cierra lo suyo; la obra entera, todo.
            'CHAPTER' === $event->scope ? $event->chapterId : null,
            CorrectionClosed::WORK_BLOCKED,
            $event->occurredAt(),
        );
    }

    public function archived(WorkArchived $event): void
    {
        $this->close($event->workId, null, CorrectionClosed::WORK_ARCHIVED, $event->occurredAt());
    }

    public function hidden(ChapterVisibilityChanged $event): void
    {
        if (ChapterVisibilityChanged::HIDDEN !== $event->visibility) {
            return;
        }

        $this->close($event->workId, $event->chapterId, CorrectionClosed::CHAPTER_HIDDEN, $event->occurredAt());
    }

    private function close(string $workId, ?string $chapterId, string $reason, \DateTimeImmutable $now): void
    {
        try {
            $work = WorkId::fromString($workId);
            $chapter = null === $chapterId ? null : ChapterId::fromString($chapterId);
        } catch (InvalidValue) {
            return;
        }

        foreach ($this->corrections->draftsOn(null === $chapter ? $work : null, $chapter) as $draft) {
            $readerId = $draft->readerId();

            if (null === $readerId) {
                continue;
            }

            // Una vez y solo una. El transporte entrega al menos una vez, y
            // sin esta marca cada reentrega publicaría otro aviso — y la
            // cascada no pararía nunca.
            $announces = $this->session->execute(function () use ($draft, $now): bool {
                if (!$draft->noteWithdrawalNotice($now)) {
                    return false;
                }

                $this->corrections->save($draft);

                return true;
            });

            if (!$announces) {
                continue;
            }

            $this->events->publish(new CorrectionClosed(
                EventId::generate(),
                $draft->id(),
                $draft->chapterId(),
                $draft->workId(),
                $readerId,
                $reason,
                $now,
            ));
        }
    }
}
