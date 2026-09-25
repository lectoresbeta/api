<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\Handler;

use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\Work\Manuscript\Application\Command\RestoreWork;
use LectoresBeta\Work\Manuscript\Domain\Event\WorkRestored;
use LectoresBeta\Work\Manuscript\Domain\Exception\WorkNotArchived;
use LectoresBeta\Work\Manuscript\Domain\Exception\WorkNotFound;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * Recuperar una obra retirada (`FEAT-WRK-006` `RN-7`).
 *
 * Vuelve **a borrador**, nunca publicada ni en corrección. Reabrir la puerta
 * es una decisión aparte, y tomarla por el autor sería volver a enseñar a
 * lectores beta una obra que él había retirado — que es exactamente lo que no
 * quería.
 */
final readonly class RestoreWorkHandler
{
    public function __construct(
        private WorkRepository $works,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(RestoreWork $command): void
    {
        try {
            $work = $this->works->ofId(WorkId::fromString($command->workId));
            $author = AuthorId::fromString($command->authorId);
        } catch (InvalidValue) {
            throw WorkNotFound::create();
        }

        if (null === $work || !$work->authorId()->equals($author)) {
            throw WorkNotFound::create();
        }

        if (!$work->isArchived()) {
            throw WorkNotArchived::create();
        }

        $now = $this->clock->now();
        $work->restore($now);

        $this->session->execute(function () use ($work): void {
            $this->works->save($work);
        });

        $this->events->publish(new WorkRestored(
            EventId::generate(),
            $work->id(),
            $work->authorId(),
            $now,
        ));
    }
}
