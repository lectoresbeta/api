<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Application\Handler;

use LectoresBeta\Credits\Account\Application\Event\FeedbackSubmitted;
use LectoresBeta\Credits\Account\Application\Service\AnnounceMovement;
use LectoresBeta\Credits\Account\Domain\Entity\CreditAccount;
use LectoresBeta\Credits\Account\Domain\Enum\CreditTransactionReason;
use LectoresBeta\Credits\Account\Domain\Repository\CreditAccountRepository;
use LectoresBeta\Credits\Account\Domain\Repository\CreditTransactionRepository;
use LectoresBeta\Credits\Account\Domain\ValueObject\CreditTransactionId;
use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Credits\EventProcessing\Domain\Entity\ProcessedEvent;
use LectoresBeta\Credits\EventProcessing\Domain\Repository\ProcessedEventRepository;
use LectoresBeta\Credits\Pricing\Application\Service\RefreshCorrectability;
use LectoresBeta\Credits\Pricing\Domain\Repository\ChapterPriceRepository;
use LectoresBeta\Credits\Pricing\Domain\Repository\CorrectionPriceRepository;
use LectoresBeta\Credits\Pricing\Domain\Service\ChapterPricing;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\ChapterId;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * The only moment credits move (`FEAT-CRD-006`).
 *
 * A reader delivered a correction, so **the author pays and the reader earns
 * the same figure**. Not two decisions: one transfer, written as two
 * movements that commit together or not at all (`RN-5`). Apart, a crash
 * between them would pay a reader with credits nobody was charged for, and
 * the accounting invariant of this context — every balance adds up to what
 * the taps issued — would be broken with no way to tell where.
 *
 * The figure is **the one quoted when the correction started** (`RN-1`), not
 * today's. Somebody who took on a job under certain terms keeps them, however
 * much the author has since enlarged the chapter or the questionnaire.
 *
 * And the reader is paid **whether or not the author can afford it** (`RN-3`).
 * That is what nothing being held back costs, and what it buys: the balance
 * goes under, the correction arrives locked
 * ([`FEAT-CRD-018`](../../../../../docs/features/credits/FEAT-CRD-018-negative-balance.md)),
 * and nobody ever writes a correction for free.
 */
final readonly class ChargeCorrectionOnFeedbackSubmitted
{
    /**
     * Names the **rule**. `FeedbackSubmitted` is also the fact an invitation
     * reward watches for (`FEAT-CRD-005`), and with the event id alone the
     * first rule to see it would close it for the other
     * (`FEAT-CRD-011` `RN-4`).
     */
    private const CONSUMER = 'correction-charge';

    public function __construct(
        private CreditAccountRepository $accounts,
        private CreditTransactionRepository $transactions,
        private CorrectionPriceRepository $quotations,
        private ChapterPriceRepository $prices,
        private ProcessedEventRepository $processedEvents,
        private AnnounceMovement $announce,
        private RefreshCorrectability $correctability,
        private ChapterPricing $pricing,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(FeedbackSubmitted $event): void
    {
        if ($this->processedEvents->wasProcessed($event->eventId(), self::CONSUMER)) {
            return;
        }

        $authorId = UserId::fromString($event->authorId);
        $readerId = UserId::fromString($event->readerId);
        $now = $this->clock->now();

        if ($authorId->equals($readerId)) {
            // Nobody corrects their own work (`FEAT-CRD-009` `RN-7`), so this
            // is a malformed fact rather than a transfer of zero. Recorded as
            // seen, and no movement: charging an account and crediting the
            // same one would net to nothing while leaving two movements in a
            // history that is supposed to explain the balance.
            $this->session->execute(fn () => $this->markProcessed($event, $now));

            return;
        }

        $quotation = $this->quotations->quoted(ChapterId::fromString($event->chapterId), $readerId);
        $amount = $quotation?->amount() ?? $this->reconstructedPrice($event);

        $author = $this->accounts->ofUser($authorId) ?? new CreditAccount($authorId, $now);
        $reader = $this->accounts->ofUser($readerId) ?? new CreditAccount($readerId, $now);

        $authorBefore = $author->balance();
        $readerBefore = $reader->balance();

        $metadata = [
            'correctionId' => $event->correctionId,
            'chapterId' => $event->chapterId,
            'workId' => $event->workId,
            // A reconstruction is a repair, and a repair that leaves no trace
            // is indistinguishable from the real thing when somebody audits
            // the movement months later (`RN-7`).
            'reconstructedPrice' => null === $quotation,
        ];

        $charge = $author->apply(
            CreditTransactionId::generate(),
            -$amount,
            CreditTransactionReason::CORRECTION_CHARGED,
            $now,
            $event->eventId(),
            [...$metadata, 'readerId' => $event->readerId],
        );

        $earning = $reader->apply(
            CreditTransactionId::generate(),
            $amount,
            CreditTransactionReason::CORRECTION_EARNED,
            $now,
            $event->eventId(),
            [...$metadata, 'authorId' => $event->authorId],
        );

        $this->session->execute(function () use ($author, $reader, $charge, $earning, $quotation, $event, $now): void {
            $this->accounts->save($author);
            $this->accounts->save($reader);
            $this->transactions->add($charge);
            $this->transactions->add($earning);

            if (null !== $quotation) {
                // Consumed, not released: there was never anything set aside.
                $this->quotations->discard($quotation);
            }

            $this->markProcessed($event, $now);
        });

        $this->announce->of($charge, $authorBefore, $author->balance());
        $this->announce->of($earning, $readerBefore, $reader->balance());

        // Both balances moved, and both people may be authors: one can no
        // longer pay for what they were offering, the other now can.
        $this->correctability->forAuthor($authorId);
        $this->correctability->forAuthor($readerId);
    }

    /**
     * `RN-7`: a delivery whose quotation is missing — lost, purged, or
     * arriving from a correction that started before this context did — is
     * **not** priced at zero and not refused. The current price of the
     * chapter is the closest honest answer, and the movement says it was
     * reconstructed.
     */
    private function reconstructedPrice(FeedbackSubmitted $event): int
    {
        $chapter = $this->prices->ofChapter(ChapterId::fromString($event->chapterId));

        return $chapter?->price() ?? $this->pricing->priceOf(0, 0);
    }

    private function markProcessed(FeedbackSubmitted $event, \DateTimeImmutable $now): void
    {
        $this->processedEvents->markProcessed(
            new ProcessedEvent($event->eventId(), self::CONSUMER, $event->eventName(), $now),
        );
    }
}
