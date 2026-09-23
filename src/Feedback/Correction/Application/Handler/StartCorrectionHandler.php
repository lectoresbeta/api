<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Handler;

use LectoresBeta\Feedback\Correction\Application\Command\StartCorrection;
use LectoresBeta\Feedback\Correction\Application\Service\EligibleCorrectionBrief;
use LectoresBeta\Feedback\Correction\Domain\Entity\Correction;
use LectoresBeta\Feedback\Correction\Domain\Event\CorrectionStarted;
use LectoresBeta\Feedback\Correction\Domain\Exception\ChapterNotCorrectable;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectableChapterRepository;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionRepository;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\AuthorId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\ChapterId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\ReaderId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Opening the correction panel (`FEAT-FBK-003`, `FEAT-CRD-009`).
 *
 * It **takes a slot and fixes a price**, which is why it is a write and not
 * the side effect of reading the questionnaire: a chapter admits three
 * corrections at a time, and a safe method that quietly consumed one of them
 * would let a page refresh close a chapter.
 *
 * In a `PUBLIC` work, starting is itself the permission (`R-4`). Nothing is
 * asked of the author and nothing is waited for: the reader was willing to
 * work, and a «espera a que te acepten» in that exact moment is where the
 * product loses people.
 *
 * Starting twice returns the same correction. It is the reader coming back to
 * a panel they left open, and a second one would be a second slot and a
 * second price for one piece of work.
 */
final readonly class StartCorrectionHandler
{
    public function __construct(
        private EligibleCorrectionBrief $eligible,
        private CorrectionRepository $corrections,
        private CorrectableChapterRepository $correctable,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(StartCorrection $command): string
    {
        $brief = $this->eligible->for($command->chapterId, $command->readerId);

        $chapterId = ChapterId::fromString($command->chapterId);
        $readerId = ReaderId::fromString($command->readerId);
        $started = $this->corrections->ofReaderAndChapter($readerId, $chapterId);

        if (null !== $started) {
            if (!$started->isDraft()) {
                throw ChapterNotCorrectable::becauseItWasAlreadyCorrected();
            }

            return $started->id()->value();
        }

        if (!$this->isTakingCorrections($chapterId)) {
            throw ChapterNotCorrectable::becauseItIsNotTakingCorrectionsNow();
        }

        $now = $this->clock->now();
        $correction = Correction::start(
            CorrectionId::generate(),
            WorkId::fromString($brief->workId),
            $chapterId,
            $readerId,
            AuthorId::fromString($brief->authorId),
            $brief->questionnaireVersion,
            $now,
        );

        $this->session->execute(function () use ($correction): void {
            $this->corrections->save($correction);
        });

        $this->events->publish(new CorrectionStarted(
            EventId::generate(),
            $chapterId,
            WorkId::fromString($brief->workId),
            AuthorId::fromString($brief->authorId),
            $readerId,
            $now,
        ));

        return $correction->id()->value();
    }

    /**
     * A chapter nobody has said anything about yet is treated as **open**.
     *
     * The projection is fed by `Credits`, and refusing while it is empty
     * would mean that a hiccup in the queue stops the product doing the one
     * thing it exists for. The cost of the opposite mistake is already an
     * accepted outcome: the author ends up in debt and the reader is paid
     * regardless (`FEAT-CRD-009` `RN-5`).
     */
    private function isTakingCorrections(ChapterId $chapterId): bool
    {
        return $this->correctable->ofChapter($chapterId)?->isCorrectable() ?? true;
    }
}
