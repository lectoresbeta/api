<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Application\Handler;

use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\Work\Chapter\Application\Command\AddChapter;
use LectoresBeta\Work\Chapter\Application\Port\ContentSanitiser;
use LectoresBeta\Work\Chapter\Domain\Entity\Chapter;
use LectoresBeta\Work\Chapter\Domain\Event\ChapterContentUpdated;
use LectoresBeta\Work\Chapter\Domain\Exception\EmptyChapter;
use LectoresBeta\Work\Chapter\Domain\Repository\ChapterRepository;
use LectoresBeta\Work\Chapter\Domain\Service\WordCounter;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId;
use LectoresBeta\Work\Manuscript\Domain\Exception\WorkNotFound;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\AuthorId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * Adding a chapter to a work (`FEAT-WRK-001`).
 *
 * The content is **sanitised before anything else looks at it**, and the
 * emptiness check happens on the result: a paste that was nothing but `div`s
 * and images survives the whitelist as an empty string, and the author
 * deserves to be told that instead of ending up with a chapter that silently
 * holds nothing.
 *
 * The totals on the work are **read back from its chapters** and only then
 * adjusted for the one being inserted, which is not yet visible to a query.
 * Keeping a running counter on the work and trusting it would be cheaper and
 * would drift exactly once, silently, about a number the price of every
 * correction depends on (`FEAT-CRD-016`).
 *
 * The chapter's word count leaves here as a fact —`ChapterContentUpdated`—
 * and nothing else. What that length is worth in credits is decided in
 * `Credits`, which is the whole point of publishing a fact instead of an
 * amount.
 */
final readonly class AddChapterHandler
{
    public function __construct(
        private WorkRepository $works,
        private ChapterRepository $chapters,
        private ContentSanitiser $sanitiser,
        private WordCounter $words,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(AddChapter $command): string
    {
        $work = $this->works->ofId(WorkId::fromString($command->workId));

        // Not yours and not there are the same answer: confirming that a work
        // exists is already a leak.
        if (null === $work || !$work->authorId()->equals(AuthorId::fromString($command->authorId))) {
            throw WorkNotFound::create();
        }

        $content = $this->sanitiser->sanitise($command->contentHtml);

        if ('' === $content->text) {
            throw EmptyChapter::create();
        }

        $now = $this->clock->now();
        $storedChapters = $this->chapters->countOfWork($work->id());
        $storedWords = $this->chapters->wordCountOfWork($work->id());

        $chapter = new Chapter(
            ChapterId::generate(),
            $work->id(),
            $storedChapters + 1,
            $now,
            $command->title,
        );
        $chapter->replaceContent($content, $this->words, $now);

        $this->session->execute(function () use ($work, $chapter, $storedChapters, $storedWords, $now): void {
            $this->chapters->save($chapter);

            $work->recountContent(
                $storedWords + $chapter->wordCount(),
                $storedChapters + 1,
                $now,
            );
            $this->works->save($work);
        });

        // Published after the transaction commits: a consumer that priced a
        // chapter this process then rolled back would be quoting a text that
        // does not exist.
        $this->events->publish(new ChapterContentUpdated(
            EventId::generate(),
            $chapter->id(),
            $work->id(),
            $work->authorId(),
            $chapter->position(),
            $chapter->wordCount(),
            $now,
        ));

        return $chapter->id()->value();
    }
}
