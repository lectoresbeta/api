<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Application\Handler;

use LectoresBeta\Reading\BetaReaderAccess\Application\Contract\BetaReaderAccessCheck;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\User\Account\Application\Contract\ReaderMaturity;
use LectoresBeta\Work\Chapter\Application\DTO\ChapterView;
use LectoresBeta\Work\Chapter\Application\Query\GetChapter;
use LectoresBeta\Work\Chapter\Domain\Enum\ChapterVisibility;
use LectoresBeta\Work\Chapter\Domain\Exception\ChapterNotFound;
use LectoresBeta\Work\Chapter\Domain\Repository\ChapterRepository;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository;
use LectoresBeta\Work\Manuscript\Domain\Service\WorkReadPolicy;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;

/**
 * Reading the text of a chapter (`FEAT-WRK-004`).
 *
 * **The work decides.** A chapter is never authorised on its own: whether
 * somebody may read it is a property of the work it belongs to, and asking
 * the chapter would be asking the wrong thing — a public chapter of a draft
 * novel is still a draft.
 *
 * On top of that, its own visibility: a hidden or blocked chapter is only its
 * author's. Every refusal is the same refusal.
 */
final readonly class GetChapterHandler
{
    public function __construct(
        private ChapterRepository $chapters,
        private WorkRepository $works,
        private WorkReadPolicy $policy,
        private ReaderMaturity $maturity,
        private BetaReaderAccessCheck $access,
    ) {
    }

    public function __invoke(GetChapter $query): ChapterView
    {
        try {
            $chapter = $this->chapters->ofId(ChapterId::fromString($query->chapterId));
        } catch (InvalidValue) {
            throw ChapterNotFound::create();
        }

        if (null === $chapter) {
            throw ChapterNotFound::create();
        }

        $work = $this->works->ofId($chapter->workId());
        $reader = AuthorId::fromString($query->readerId);

        if (null === $work || !$this->policy->allows(
            $work,
            $reader,
            $this->maturity->isOfAge($query->readerId),
            $this->access->hasAccessTo($work->id()->value(), $query->readerId),
        )) {
            throw ChapterNotFound::create();
        }

        $isAuthor = $work->authorId()->equals($reader);

        if (!$isAuthor && (ChapterVisibility::VISIBLE !== $chapter->visibility() || $chapter->isBlocked())) {
            throw ChapterNotFound::create();
        }

        return new ChapterView(
            $chapter->id()->value(),
            $chapter->workId()->value(),
            $chapter->position(),
            $chapter->title(),
            $chapter->content()->html,
            $chapter->wordCount(),
        );
    }
}
