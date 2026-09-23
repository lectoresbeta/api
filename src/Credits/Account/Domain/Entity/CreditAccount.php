<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Domain\Entity;

use LectoresBeta\Credits\Account\Domain\Enum\CreditTransactionReason;
use LectoresBeta\Credits\Account\Domain\ValueObject\CreditTransactionId;
use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;

/**
 * Somebody's credit balance (`decision:0006`).
 *
 * **One balance, and it may be negative.** There is no «available balance»,
 * because nothing is ever held back: what the author sees is what they have.
 * A correction is charged on delivery, and if the balance does not cover it
 * the corrector is still paid and the author goes negative (`RN-9`).
 *
 * The stored `balance` is a running total kept for reading. The truth is the
 * sum of the movements, and `FEAT-CRD-012` recomputes it to check they still
 * agree.
 */
class CreditAccount
{
    private string $userId;

    private int $balance = 0;

    /**
     * How many invitation rewards this account has already collected. Capped
     * at ten (`decision:0006`, rule 6) so nobody builds a balance by
     * recruiting instead of correcting.
     */
    private int $invitationRewards = 0;

    private \DateTimeImmutable $createdAt;

    private \DateTimeImmutable $updatedAt;

    private ?\DateTimeImmutable $anonymisedAt = null;

    public function __construct(UserId $userId, \DateTimeImmutable $now)
    {
        $this->userId = $userId->value();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function userId(): UserId
    {
        return UserId::fromString($this->userId);
    }

    public function balance(): int
    {
        return $this->balance;
    }

    public function isInDebt(): bool
    {
        return $this->balance < 0;
    }

    public function invitationRewards(): int
    {
        return $this->invitationRewards;
    }

    /**
     * Whether a chapter at this price can be opened for correction. With a
     * negative balance no new correction starts, but the holder can still
     * correct — that is how the debt gets paid off (`RN-10`).
     */
    public function canAfford(int $price): bool
    {
        return $this->balance >= $price;
    }

    /**
     * Applies a movement and returns it, so the caller persists exactly what
     * was applied. The balance is never set directly.
     *
     * @param array<string, scalar|null> $metadata
     */
    public function apply(
        CreditTransactionId $transactionId,
        int $amount,
        CreditTransactionReason $reason,
        \DateTimeImmutable $now,
        ?string $eventId = null,
        array $metadata = [],
    ): CreditTransaction {
        $this->balance += $amount;
        $this->updatedAt = $now;

        if (CreditTransactionReason::INVITATION_REWARD === $reason) {
            ++$this->invitationRewards;
        }

        return new CreditTransaction(
            $transactionId,
            $this->userId(),
            $amount,
            $reason,
            $now,
            $eventId,
            $metadata,
        );
    }

    /**
     * Deleting the account does not settle its debt and does not remove its
     * movements (`C-21`): accounting-wise an unpaid debt is issuance, and
     * pretending otherwise would break `RN-11`.
     */
    public function anonymise(\DateTimeImmutable $now): void
    {
        $this->anonymisedAt = $now;
        $this->updatedAt = $now;
    }
}
