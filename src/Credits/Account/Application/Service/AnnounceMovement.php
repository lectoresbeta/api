<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Application\Service;

use LectoresBeta\Credits\Account\Domain\Entity\CreditTransaction;
use LectoresBeta\Credits\Account\Domain\Event\CreditBalanceChanged;
use LectoresBeta\Credits\Account\Domain\Event\CreditBalanceWentNegative;
use LectoresBeta\Credits\Account\Domain\Event\CreditsAdded;
use LectoresBeta\Credits\Account\Domain\Event\CreditsSpent;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * Telling the rest of the system that a balance moved (`FEAT-CRD-006`).
 *
 * Two events per movement, and they answer different questions. `CreditsAdded`
 * and `CreditsSpent` say **what happened and why**, which is what a notice to
 * the person is written from. `CreditBalanceChanged` says **what the figure
 * is now**, which is what a read model needs and the only one that can be
 * applied out of order without harm.
 *
 * And a third, only on the movement that takes an account under zero.
 *
 * It is called **after** the transaction that applied the movement: an event
 * published from a state that then rolled back is a lie nobody can retract.
 */
final readonly class AnnounceMovement
{
    public function __construct(private EventPublisher $events)
    {
    }

    public function of(CreditTransaction $movement, int $balanceBefore, int $balanceAfter): void
    {
        $announcements = [$this->narrated($movement, $balanceAfter)];

        $announcements[] = new CreditBalanceChanged(
            EventId::generate(),
            $movement->userId(),
            $balanceAfter,
            $movement->occurredAt(),
        );

        // The crossing, not the state. Published while it happens and never
        // again while the account stays under.
        if ($balanceBefore >= 0 && $balanceAfter < 0) {
            $announcements[] = new CreditBalanceWentNegative(
                EventId::generate(),
                $movement->userId(),
                $balanceAfter,
                $movement->occurredAt(),
            );
        }

        $this->events->publish(...$announcements);
    }

    private function narrated(CreditTransaction $movement, int $balanceAfter): IntegrationEvent
    {
        if ($movement->amount() < 0) {
            return new CreditsSpent(
                EventId::generate(),
                $movement->userId(),
                abs($movement->amount()),
                $movement->reason()->value,
                $balanceAfter,
                $movement->occurredAt(),
            );
        }

        return new CreditsAdded(
            EventId::generate(),
            $movement->userId(),
            $movement->amount(),
            $movement->reason()->value,
            $balanceAfter,
            $movement->occurredAt(),
        );
    }
}
