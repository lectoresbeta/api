<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Application\Service;

use LectoresBeta\Credits\Account\Domain\Repository\CreditAccountRepository;
use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Credits\Pricing\Domain\Entity\ChapterPrice;
use LectoresBeta\Credits\Pricing\Domain\Event\ChapterCorrectabilityChanged;
use LectoresBeta\Credits\Pricing\Domain\Repository\ChapterPriceRepository;
use LectoresBeta\Credits\Pricing\Domain\Repository\CorrectionPriceRepository;
use LectoresBeta\Credits\Pricing\Domain\Service\CorrectabilityPolicy;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Keeping the outside world's answer to «can this chapter be corrected?» up
 * to date (`FEAT-CRD-009`).
 *
 * Correctability is derived from three things that move independently: the
 * author's balance, the chapter's price and how many corrections are already
 * open on it. So this is called from everywhere any of the three changes —
 * a chapter's text, a questionnaire, a movement, a correction started or
 * discarded — rather than being computed when somebody asks.
 *
 * **Only changes are published.** A price is recomputed far more often than
 * its yes-or-no answer actually moves, and a context that received an event
 * every time would learn nothing from receiving one.
 *
 * It runs **after** the transaction of whatever called it. The read model is
 * the source of truth of nothing, so there is no atomicity to preserve here;
 * what would be wrong is publishing a fact derived from state that then
 * rolled back.
 */
final readonly class RefreshCorrectability
{
    public function __construct(
        private ChapterPriceRepository $prices,
        private CorrectionPriceRepository $quotations,
        private CreditAccountRepository $accounts,
        private CorrectabilityPolicy $policy,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    /**
     * A balance moved: every chapter of that author may have flipped at once.
     */
    public function forAuthor(UserId $authorId): void
    {
        $this->refresh($this->prices->ofAuthor($authorId));
    }

    /**
     * A price moved, which happens per work: the questionnaire is the work's
     * and a new chapter reprices its siblings.
     */
    public function forWork(WorkId $workId): void
    {
        $this->refresh($this->prices->ofWork($workId));
    }

    /**
     * @param list<ChapterPrice> $chapters
     */
    public function refresh(array $chapters): void
    {
        if ([] === $chapters) {
            return;
        }

        $now = $this->clock->now();
        $balances = [];
        $changed = [];

        foreach ($chapters as $chapter) {
            $author = $chapter->authorId()->value();
            // One lookup per author and not one per chapter: a novel of forty
            // chapters has one balance.
            $balances[$author] ??= $this->accounts->ofUser($chapter->authorId())?->balance() ?? 0;

            $correctable = $this->policy->allows(
                $balances[$author],
                $chapter->price(),
                $this->quotations->openCorrectionsOn($chapter->chapterId()),
            );

            if ($chapter->updateCorrectability($correctable, $now)) {
                $changed[] = $chapter;
            }
        }

        if ([] === $changed) {
            return;
        }

        $this->session->execute(function () use ($changed): void {
            foreach ($changed as $chapter) {
                $this->prices->save($chapter);
            }
        });

        $this->events->publish(...array_map(
            static fn (ChapterPrice $chapter): ChapterCorrectabilityChanged => new ChapterCorrectabilityChanged(
                EventId::generate(),
                $chapter->chapterId(),
                $chapter->workId(),
                $chapter->isCorrectable(),
                $now,
            ),
            $changed,
        ));
    }
}
