<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\Handler;

use LectoresBeta\Reading\BetaReaderAccess\Application\Contract\BetaReaderAccessCheck;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\User\Account\Application\Contract\ReaderMaturity;
use LectoresBeta\Work\Chapter\Domain\Entity\Chapter;
use LectoresBeta\Work\Chapter\Domain\Enum\ChapterVisibility;
use LectoresBeta\Work\Chapter\Domain\Repository\ChapterRepository;
use LectoresBeta\Work\Manuscript\Application\DTO\ChapterSummary;
use LectoresBeta\Work\Manuscript\Application\DTO\WorkView;
use LectoresBeta\Work\Manuscript\Application\Query\GetWork;
use LectoresBeta\Work\Manuscript\Domain\Exception\WorkNotFound;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository;
use LectoresBeta\Work\Manuscript\Domain\Service\WorkReadPolicy;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * Reading a work's metadata and index (`FEAT-WRK-004`).
 *
 * **Every refusal is the same refusal**: `WorkNotFound`, whether the work is
 * absent, a stranger's draft, restricted, blocked or adults-only. Any
 * observable difference is information about unpublished writing, and that is
 * the asset this platform exists to protect.
 *
 * Hidden chapters drop out of the index for everybody but the author
 * (`RN-4`): leaving a gap in the numbering would say there is something
 * there.
 */
final readonly class GetWorkHandler
{
    public function __construct(
        private WorkRepository $works,
        private ChapterRepository $chapters,
        private WorkReadPolicy $policy,
        private ReaderMaturity $maturity,
        private BetaReaderAccessCheck $access,
    ) {
    }

    public function __invoke(GetWork $query): WorkView
    {
        try {
            $work = $this->works->ofId(WorkId::fromString($query->workId));
        } catch (InvalidValue) {
            throw WorkNotFound::create();
        }

        $reader = AuthorId::fromString($query->readerId);

        if (null === $work || !$this->policy->allows(
            $work,
            $reader,
            $this->maturity->isOfAge($query->readerId),
            $this->access->hasAccessTo($work->id()->value(), $query->readerId),
        )) {
            throw WorkNotFound::create();
        }

        $isAuthor = $work->authorId()->equals($reader);

        $chapters = array_values(array_filter(
            $this->chapters->ofWork($work->id()),
            static fn (Chapter $chapter): bool => $isAuthor
                || (ChapterVisibility::VISIBLE === $chapter->visibility() && !$chapter->isBlocked()),
        ));

        return new WorkView(
            $work->id()->value(),
            $work->authorId()->value(),
            $work->title()->value(),
            $work->synopsis(),
            $work->status()->value,
            $work->accessMode()->value,
            $work->isAdultsOnly(),
            $work->wordCount(),
            array_map(
                static fn (Chapter $chapter): ChapterSummary => new ChapterSummary(
                    $chapter->id()->value(),
                    $chapter->position(),
                    $chapter->title(),
                    $chapter->wordCount(),
                ),
                $chapters,
            ),
            $work->isBlocked(),
        );
    }
}
