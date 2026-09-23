<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Application\Handler;

use LectoresBeta\Credits\EventProcessing\Domain\Entity\ProcessedEvent;
use LectoresBeta\Credits\EventProcessing\Domain\Repository\ProcessedEventRepository;
use LectoresBeta\Credits\Pricing\Application\Event\QuestionnaireUpdated;
use LectoresBeta\Credits\Pricing\Domain\Entity\WorkQuestionnaireDemand;
use LectoresBeta\Credits\Pricing\Domain\Repository\ChapterPriceRepository;
use LectoresBeta\Credits\Pricing\Domain\Repository\WorkQuestionnaireDemandRepository;
use LectoresBeta\Credits\Pricing\Domain\Service\WorkPricing;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * A new questionnaire reprices every chapter of its work (`FEAT-CRD-016`).
 *
 * The demand is stored even when the work has no chapters yet, and that is
 * the point of storing it at all: the two facts that make a price arrive
 * separately and in either order, so a chapter added next week still has to
 * find out what the author asks for.
 *
 * `Credits` decides what the demand is worth. `Work` publishes how many words
 * it asks for and never an amount — the direction that
 * [`decision:0002`](../../../../../docs/decisions/0002-credits-as-isolated-bounded-context.md)
 * exists to keep.
 *
 * Repricing reaches **nobody who is already correcting**: what they are owed
 * was quoted when they started (`RN-4`). An author can rewrite the
 * questionnaire without anything moving under a reader's feet.
 */
final readonly class RepriceWorkOnQuestionnaireUpdate
{
    private const CONSUMER = 'questionnaire-pricing';

    public function __construct(
        private ChapterPriceRepository $prices,
        private WorkQuestionnaireDemandRepository $demands,
        private ProcessedEventRepository $processedEvents,
        private WorkPricing $workPricing,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(QuestionnaireUpdated $event): void
    {
        if ($this->processedEvents->wasProcessed($event->eventId(), self::CONSUMER)) {
            return;
        }

        $workId = WorkId::fromString($event->workId);
        $now = $this->clock->now();
        $demand = $this->demands->ofWork($workId);

        if (null !== $demand && !$demand->update($event->version, $event->requiredWords, $event->requiredWordsForEveryChapter, $now)) {
            // An older version arriving late, which the queue is allowed to
            // do. Recorded as processed all the same: otherwise every
            // redelivery would run this again for ever.
            $this->session->execute(fn () => $this->markProcessed($event, $now));

            return;
        }

        $demand ??= new WorkQuestionnaireDemand(
            $workId,
            $event->version,
            $event->requiredWords,
            $event->requiredWordsForEveryChapter,
            $now,
        );

        $chapters = $this->prices->ofWork($workId);

        $this->workPricing->reprice($chapters, $demand, $now);

        $this->session->execute(function () use ($demand, $chapters, $event, $now): void {
            $this->demands->save($demand);

            foreach ($chapters as $priced) {
                $this->prices->save($priced);
            }

            $this->markProcessed($event, $now);
        });
    }

    private function markProcessed(QuestionnaireUpdated $event, \DateTimeImmutable $now): void
    {
        $this->processedEvents->markProcessed(
            new ProcessedEvent($event->eventId(), self::CONSUMER, $event->eventName(), $now),
        );
    }
}
