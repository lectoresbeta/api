<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Handler;

use LectoresBeta\Feedback\Correction\Application\Event\ChapterCorrectabilityChanged;
use LectoresBeta\Feedback\Correction\Domain\Entity\CorrectableChapter;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectableChapterRepository;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\ChapterId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Keeping this context's own answer to «can this chapter be corrected?»
 * (`FEAT-CRD-009`).
 *
 * The projection is what lets the correction panel open **without asking
 * `Credits` anything**, which is the whole reason nothing is held back any
 * more: there is no reservation to negotiate, so there is nothing to wait
 * for.
 *
 * No deduplication ledger here, and not by omission: the projection is
 * idempotent by construction — the last word wins, and the entity itself
 * discards anything older than what it already holds.
 */
final readonly class TrackChapterCorrectability
{
    public function __construct(
        private CorrectableChapterRepository $chapters,
        private TransactionalSession $session,
    ) {
    }

    public function __invoke(ChapterCorrectabilityChanged $event): void
    {
        $chapterId = ChapterId::fromString($event->chapterId);
        $known = $this->chapters->ofChapter($chapterId);

        if (null === $known) {
            $known = new CorrectableChapter(
                $chapterId,
                WorkId::fromString($event->workId),
                $event->correctable,
                $event->occurredAt(),
            );
        } else {
            $known->record($event->correctable, $event->occurredAt());
        }

        $this->session->execute(function () use ($known): void {
            $this->chapters->save($known);
        });
    }
}
