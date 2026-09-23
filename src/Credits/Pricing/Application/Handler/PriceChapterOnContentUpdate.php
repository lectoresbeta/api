<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Application\Handler;

use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Credits\EventProcessing\Domain\Entity\ProcessedEvent;
use LectoresBeta\Credits\EventProcessing\Domain\Repository\ProcessedEventRepository;
use LectoresBeta\Credits\Pricing\Application\Event\ChapterContentUpdated;
use LectoresBeta\Credits\Pricing\Application\Service\RefreshCorrectability;
use LectoresBeta\Credits\Pricing\Domain\Entity\ChapterPrice;
use LectoresBeta\Credits\Pricing\Domain\Repository\ChapterPriceRepository;
use LectoresBeta\Credits\Pricing\Domain\Repository\WorkQuestionnaireDemandRepository;
use LectoresBeta\Credits\Pricing\Domain\Service\ChapterPricing;
use LectoresBeta\Credits\Pricing\Domain\Service\WorkPricing;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\ChapterId;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * What a chapter costs to correct, kept up to date (`FEAT-CRD-016`).
 *
 * `Work` says how long a chapter is; what that is worth is decided here, and
 * `Work` never learns the answer unless it asks for it through a contract.
 *
 * **The whole work is repriced, not just this chapter.** A chapter arriving
 * at a further position moves where the work ends, and the one that used to
 * be last stops answering the questions of scope `LAST_CHAPTER`
 * (`FEAT-WRK-014` `W-17`). Repricing only the new row would leave its
 * predecessor charging for a question nobody will answer in it.
 *
 * Nothing here is charged to anybody: this is a read model. What a reader is
 * owed is frozen separately when they start correcting (`RN-4`), so a price
 * that moves — or a row that is briefly wrong — never reaches somebody
 * mid-correction.
 */
final readonly class PriceChapterOnContentUpdate
{
    /**
     * Names the **rule**, not this class: renaming the class must not reopen
     * events that were already applied (`FEAT-CRD-011` `RN-4`).
     */
    private const CONSUMER = 'chapter-pricing';

    public function __construct(
        private ChapterPriceRepository $prices,
        private WorkQuestionnaireDemandRepository $demands,
        private ProcessedEventRepository $processedEvents,
        private WorkPricing $workPricing,
        private RefreshCorrectability $correctability,
        private ChapterPricing $pricing,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(ChapterContentUpdated $event): void
    {
        if ($this->processedEvents->wasProcessed($event->eventId(), self::CONSUMER)) {
            return;
        }

        $workId = WorkId::fromString($event->workId);
        $chapterId = ChapterId::fromString($event->chapterId);
        $now = $this->clock->now();

        $chapters = $this->prices->ofWork($workId);
        $chapter = null;

        foreach ($chapters as $known) {
            if ($known->chapterId()->equals($chapterId)) {
                $chapter = $known;
            }
        }

        if (null === $chapter) {
            // The row is added to the list by hand instead of being read back
            // from the repository: it is not flushed yet, so a second query
            // would not see it and the new chapter would be priced without
            // the questionnaire.
            $chapter = new ChapterPrice(
                $chapterId,
                $workId,
                UserId::fromString($event->authorId),
                $event->position,
                $event->wordCount,
                0,
                $this->pricing,
                $now,
            );
            $chapters[] = $chapter;
        } else {
            $chapter->updateContent($event->position, $event->wordCount, $this->pricing, $now);
        }

        $this->workPricing->reprice($chapters, $this->demands->ofWork($workId), $now);

        $this->session->execute(function () use ($chapters, $event, $now): void {
            foreach ($chapters as $priced) {
                $this->prices->save($priced);
            }

            $this->processedEvents->markProcessed(
                new ProcessedEvent($event->eventId(), self::CONSUMER, $event->eventName(), $now),
            );
        });

        // A different price may put a chapter within reach of the author's
        // balance, or out of it (`FEAT-CRD-009`).
        $this->correctability->forWork($workId);
    }
}
