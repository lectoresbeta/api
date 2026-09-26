<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Application\Handler;

use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Credits\EventProcessing\Domain\Entity\ProcessedEvent;
use LectoresBeta\Credits\EventProcessing\Domain\Repository\ProcessedEventRepository;
use LectoresBeta\Credits\Pricing\Application\Event\CorrectionDraftDiscarded;
use LectoresBeta\Credits\Pricing\Application\Service\RefreshCorrectability;
use LectoresBeta\Credits\Pricing\Domain\Repository\ChapterPriceRepository;
use LectoresBeta\Credits\Pricing\Domain\Repository\CorrectionPriceRepository;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\ChapterId;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * The reader gave up (`FEAT-CRD-009`).
 *
 * There is **nothing to release**: no credit was set aside when the quotation
 * was written, so dropping it is the entire operation. What it does change is
 * how many corrections are open on the chapter, which may put it back within
 * reach of the next reader.
 */
final readonly class DropQuotationOnDraftDiscarded
{
    private const CONSUMER = 'correction-quotation-drop';

    public function __construct(
        private CorrectionPriceRepository $quotations,
        private ChapterPriceRepository $prices,
        private ProcessedEventRepository $processedEvents,
        private RefreshCorrectability $correctability,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(CorrectionDraftDiscarded $event): void
    {
        if ($this->processedEvents->wasProcessed($event->eventId(), self::CONSUMER)) {
            return;
        }

        $chapterId = ChapterId::fromString($event->chapterId);
        $now = $this->clock->now();
        $quotation = $this->quotations->quoted($chapterId, UserId::fromString($event->readerId));

        $this->session->execute(function () use ($quotation, $event, $now): void {
            if (null !== $quotation) {
                $this->quotations->discard($quotation);
            }

            $this->processedEvents->markProcessed(
                new ProcessedEvent($event->eventId(), self::CONSUMER, $event->eventName(), $now),
            );
        });

        $chapter = $this->prices->ofChapter($chapterId);

        if (null !== $chapter) {
            $this->correctability->refresh([$chapter]);
        }
    }
}
