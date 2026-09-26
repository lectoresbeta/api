<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Application\Handler;

use LectoresBeta\Credits\Account\Application\Event\AccountActivated;
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
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * The welcome grant (`FEAT-CRD-002`).
 *
 * Two guards, and they are **not** the same guard twice:
 *
 * - `ProcessedEvent` stops the *same* delivery being applied again. RabbitMQ
 *   does not promise single delivery (`FEAT-CRD-011`).
 * - `hasAlreadyBeenWelcomed` stops a *different, legitimate* activation of
 *   the same account paying a second time. That one would carry a fresh event
 *   id and sail straight past the first guard (`FEAT-CRD-002` `RN-3`).
 *
 * The account is created here, lazily, and `Credits` deliberately does not
 * subscribe to `UserRegistered`: an account with no movements and a balance
 * of zero are indistinguishable, so creating it earlier would buy nothing and
 * would mean knowing about a fact that has no credit effect (`RN-5`).
 */
final readonly class GrantWelcomeCredits
{
    /**
     * Names the **rule**, not this class. Renaming the class must not reopen
     * events that were already applied (`FEAT-CRD-011` `RN-4`).
     */
    private const CONSUMER = 'welcome-grant';

    public function __construct(
        private CreditAccountRepository $accounts,
        private CreditTransactionRepository $transactions,
        private ProcessedEventRepository $processedEvents,
        private AnnounceMovement $announce,
        private RefreshCorrectability $correctability,
        private TransactionalSession $session,
        private Clock $clock,
        private int $welcomeCredits,
    ) {
    }

    public function __invoke(AccountActivated $event): void
    {
        if ($this->processedEvents->wasProcessed($event->eventId(), self::CONSUMER)) {
            return;
        }

        $userId = UserId::fromString($event->userId);
        $now = $this->clock->now();
        $account = $this->accounts->ofUser($userId) ?? new CreditAccount($userId, $now);

        if ($this->transactions->hasMovementWithReason($userId, CreditTransactionReason::WELCOME_GRANT)) {
            // Still recorded as processed: otherwise every redelivery would
            // repeat this query for ever.
            $this->session->execute(function () use ($event, $now): void {
                $this->processedEvents->markProcessed(
                    new ProcessedEvent($event->eventId(), self::CONSUMER, $event->eventName(), $now),
                );
            });

            return;
        }

        $balanceBefore = $account->balance();

        $movement = $account->apply(
            CreditTransactionId::generate(),
            $this->welcomeCredits,
            CreditTransactionReason::WELCOME_GRANT,
            $now,
            $event->eventId(),
        );

        // The movement and its deduplication row commit together or not at
        // all. Apart, a crash between them either pays twice or loses the
        // grant while marking it done (`FEAT-CRD-011` `RN-2`).
        $this->session->execute(function () use ($account, $movement, $event, $now): void {
            $this->accounts->save($account);
            $this->transactions->add($movement);
            $this->processedEvents->markProcessed(
                new ProcessedEvent($event->eventId(), self::CONSUMER, $event->eventName(), $now),
            );
        });

        $this->announce->of($movement, $balanceBefore, $account->balance());

        // Ten credits is enough to afford a correction, so chapters this
        // person already wrote may become correctable at this exact moment
        // (`FEAT-CRD-009`). Without this, an author who uploaded before
        // activating would stay invisible until they touched something.
        $this->correctability->forAuthor($userId);
    }
}
