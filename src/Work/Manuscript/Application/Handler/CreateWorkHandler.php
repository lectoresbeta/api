<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\Handler;

use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\Work\Manuscript\Application\Command\CreateWork;
use LectoresBeta\Work\Manuscript\Application\Service\DeclareGenres;
use LectoresBeta\Work\Manuscript\Domain\Entity\Work;
use LectoresBeta\Work\Manuscript\Domain\Event\WorkCreated;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkTitle;

/**
 * Creating a work (`FEAT-WRK-001`).
 *
 * Two decisions the ficha settled and that the shape of this handler makes
 * visible:
 *
 * - **the work is born in `DRAFT`** (`Q-1`). It is the only safe value:
 *   being born visible would expose unpublished writing through a slip;
 * - **creation is incremental** (`Q-2`). A work is created with its metadata
 *   and its chapters are added afterwards. A forty-chapter novel does not fit
 *   in one request, and insisting on it would turn every save into a full
 *   re-upload.
 *
 * So a work starts with no content, and that is expected: `RN-2` —a work has
 * at least one chapter— is a condition for **publishing**, not for existing.
 */
final readonly class CreateWorkHandler
{
    public function __construct(
        private WorkRepository $works,
        private DeclareGenres $genres,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(CreateWork $command): string
    {
        $now = $this->clock->now();
        $work = new Work(
            WorkId::generate(),
            AuthorId::fromString($command->authorId),
            WorkTitle::fromString($command->title),
            $now,
        );

        if (null !== $command->synopsis) {
            $work->describe($command->synopsis, $now);
        }

        $this->session->execute(function () use ($work, $command): void {
            $this->works->save($work);
            $this->genres->on($work->id(), $command->genres);
        });

        $this->events->publish(new WorkCreated(
            EventId::generate(),
            $work->id(),
            $work->authorId(),
            $work->title()->value(),
            $work->accessMode()->value,
            $now,
        ));

        return $work->id()->value();
    }
}
