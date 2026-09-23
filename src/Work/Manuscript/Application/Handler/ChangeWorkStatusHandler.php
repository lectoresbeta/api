<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\Handler;

use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\Work\Manuscript\Application\Command\ChangeWorkStatus;
use LectoresBeta\Work\Manuscript\Domain\Entity\Work;
use LectoresBeta\Work\Manuscript\Domain\Enum\WorkStatus;
use LectoresBeta\Work\Manuscript\Domain\Event\WorkClosedForCorrection;
use LectoresBeta\Work\Manuscript\Domain\Event\WorkOpenedForCorrection;
use LectoresBeta\Work\Manuscript\Domain\Event\WorkPublished;
use LectoresBeta\Work\Manuscript\Domain\Exception\IllegalWorkTransition;
use LectoresBeta\Work\Manuscript\Domain\Exception\WorkNotFound;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * Publishing a work, opening it to correction and closing it again
 * (`FEAT-WRK-016`).
 *
 * The three transitions the API offers, and the reason there is no fourth:
 *
 * | De | A | |
 * |---|---|---|
 * | `DRAFT` | `PUBLISHED` | Publicar |
 * | `PUBLISHED` | `IN_CORRECTION` | Abrir a corrección |
 * | `IN_CORRECTION` | `PUBLISHED` | Cerrar la corrección |
 *
 * **Publishing and opening are two steps, not one** (`W-10`). Skipping
 * straight from a draft to accepting corrections would hide a publication
 * inside a different action, and the author would start spending credits on a
 * text nobody has yet seen as others will see it. Two calls cost the client
 * nothing.
 *
 * **Going back to `DRAFT` is not offered.** Somebody may already have read the
 * work, and with corrections in flight it is worse: a reader who has spent
 * hours on a chapter cannot have it vanish. That one is still `W-10`'s to
 * decide, and until it does, the honest answer is «no».
 */
final readonly class ChangeWorkStatusHandler
{
    public function __construct(
        private WorkRepository $works,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(ChangeWorkStatus $command): string
    {
        $work = $this->works->ofId(WorkId::fromString($command->workId));

        // Not yours and not there are one answer: confirming that an
        // unpublished work exists is already a leak.
        if (null === $work || !$work->authorId()->equals(AuthorId::fromString($command->authorId))) {
            throw WorkNotFound::create();
        }

        $target = WorkStatus::tryFrom($command->status);

        if (null === $target) {
            throw IllegalWorkTransition::unknownStatus($command->status);
        }

        $now = $this->clock->now();
        $event = $this->apply($work, $target, $now);

        $this->session->execute(function () use ($work): void {
            $this->works->save($work);
        });

        $this->events->publish($event);

        return $work->status()->value;
    }

    private function apply(Work $work, WorkStatus $target, \DateTimeImmutable $now): IntegrationEvent
    {
        $from = $work->status();

        return match ($target) {
            WorkStatus::PUBLISHED => $this->toPublished($work, $from, $now),
            WorkStatus::IN_CORRECTION => $this->toInCorrection($work, $now),
            WorkStatus::DRAFT => throw IllegalWorkTransition::unpublishingIsNotAvailable(),
        };
    }

    private function toPublished(Work $work, WorkStatus $from, \DateTimeImmutable $now): IntegrationEvent
    {
        if (WorkStatus::IN_CORRECTION === $from) {
            $work->closeForCorrection($now);

            return new WorkClosedForCorrection(EventId::generate(), $work->id(), $work->authorId(), $now);
        }

        $work->publish($now);

        return new WorkPublished(
            EventId::generate(),
            $work->id(),
            $work->authorId(),
            $work->title()->value(),
            $work->wordCount(),
            $work->chapterCount(),
            $now,
        );
    }

    private function toInCorrection(Work $work, \DateTimeImmutable $now): IntegrationEvent
    {
        $work->openForCorrection($now);

        return new WorkOpenedForCorrection(EventId::generate(), $work->id(), $work->authorId(), $now);
    }
}
