<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\Handler;

use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\Work\Manuscript\Application\Command\SetAccessMode;
use LectoresBeta\Work\Manuscript\Domain\Enum\BetaReaderAccessMode;
use LectoresBeta\Work\Manuscript\Domain\Event\WorkAccessModeChanged;
use LectoresBeta\Work\Manuscript\Domain\Exception\UnknownAccessMode;
use LectoresBeta\Work\Manuscript\Domain\Exception\WorkNotFound;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * Who may become a beta reader of this work (`FEAT-WRK-007`).
 *
 * Changeable in any state, `DRAFT` included: preparing a work before
 * publishing it is the normal way round.
 *
 * It changes **who may come in from now on**, and revokes nothing: somebody
 * already correcting keeps their access, because taking it away mid-way would
 * destroy hours of their work (`RN-3`).
 */
final readonly class SetAccessModeHandler
{
    public function __construct(
        private WorkRepository $works,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(SetAccessMode $command): string
    {
        $work = $this->works->ofId(WorkId::fromString($command->workId));

        if (null === $work || !$work->authorId()->equals(AuthorId::fromString($command->authorId))) {
            throw WorkNotFound::create();
        }

        $mode = BetaReaderAccessMode::tryFrom($command->accessMode);

        if (null === $mode) {
            throw UnknownAccessMode::named($command->accessMode);
        }

        $now = $this->clock->now();

        $this->session->execute(function () use ($work, $mode, $now): void {
            $work->changeAccessMode($mode, $now);
            $this->works->save($work);
        });

        $this->events->publish(new WorkAccessModeChanged(
            EventId::generate(),
            $work->id(),
            $work->authorId(),
            $mode->value,
            $now,
        ));

        return $mode->value;
    }
}
