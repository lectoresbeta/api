<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Application\Handler;

use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Credits\EventProcessing\Domain\Entity\ProcessedEvent;
use LectoresBeta\Credits\EventProcessing\Domain\Repository\ProcessedEventRepository;
use LectoresBeta\Credits\Pricing\Application\Event\CorrectionStarted;
use LectoresBeta\Credits\Pricing\Application\Service\RefreshCorrectability;
use LectoresBeta\Credits\Pricing\Domain\Entity\CorrectionPrice;
use LectoresBeta\Credits\Pricing\Domain\Repository\ChapterPriceRepository;
use LectoresBeta\Credits\Pricing\Domain\Repository\CorrectionPriceRepository;
use LectoresBeta\Credits\Pricing\Domain\Service\ChapterPricing;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\ChapterId;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Quoting the price of a correction that is starting (`FEAT-CRD-009`).
 *
 * **Nothing is held.** The author's balance does not move and there is no
 * state to reconcile later: this writes down a number, and that number is
 * what will be charged and earned when the correction is delivered, however
 * long the reader takes and whatever the author edits meanwhile (`RN-2`,
 * `RN-3`, `RN-4`).
 *
 * That is the whole reason `Feedback` can open the panel without waiting for
 * an answer from here.
 *
 * A second `CorrectionStarted` for the same chapter and reader **keeps the
 * first quotation**. Re-quoting would let a reader refresh their price by
 * reopening the panel after the author enlarged the chapter, which is the
 * opposite of what `RN-2` promises.
 */
final readonly class QuoteCorrectionOnStart
{
    private const CONSUMER = 'correction-quotation';

    public function __construct(
        private CorrectionPriceRepository $quotations,
        private ChapterPriceRepository $prices,
        private ProcessedEventRepository $processedEvents,
        private RefreshCorrectability $correctability,
        private ChapterPricing $pricing,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(CorrectionStarted $event): void
    {
        if ($this->processedEvents->wasProcessed($event->eventId(), self::CONSUMER)) {
            return;
        }

        $chapterId = ChapterId::fromString($event->chapterId);
        $readerId = UserId::fromString($event->readerId);
        $now = $this->clock->now();

        if (null === $this->quotations->quoted($chapterId, $readerId)) {
            $this->session->execute(function () use ($event, $chapterId, $readerId, $now): void {
                $this->quotations->save(new CorrectionPrice(
                    $chapterId,
                    $readerId,
                    UserId::fromString($event->authorId),
                    $this->priceOf($chapterId),
                    $now,
                ));

                $this->processedEvents->markProcessed(
                    new ProcessedEvent($event->eventId(), self::CONSUMER, $event->eventName(), $now),
                );
            });
        } else {
            $this->session->execute(function () use ($event, $now): void {
                $this->processedEvents->markProcessed(
                    new ProcessedEvent($event->eventId(), self::CONSUMER, $event->eventName(), $now),
                );
            });
        }

        // One slot fewer on that chapter, which may be the third (`RN-8`).
        $this->correctability->forWork(WorkId::fromString($event->workId));
    }

    /**
     * The price this context already keeps for that chapter. When the chapter
     * is unknown — its content event has not arrived, or the read model was
     * rebuilt and is still catching up — the floor applies rather than a
     * refusal: a correction in progress that cannot be quoted would end up
     * unpaid, and `RN-3` says a reader is always paid.
     */
    private function priceOf(ChapterId $chapterId): int
    {
        return $this->prices->ofChapter($chapterId)?->price() ?? $this->pricing->priceOf(0, 0);
    }
}
