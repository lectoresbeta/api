<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Domain\Entity;

use LectoresBeta\Credits\Account\Domain\Enum\CreditTransactionReason;
use LectoresBeta\Credits\Account\Domain\ValueObject\CreditTransactionId;
use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;

/**
 * One movement of credits. Immutable: there is no mutator, and there is no
 * repository method that updates one (`RN-2`).
 *
 * Correcting a mistake means adding a new movement, never editing this one.
 * The balance is the sum of these rows and has to be recomputable from
 * scratch; an edit would make that sum disagree with history, which is
 * exactly the failure `RN-11` exists to catch.
 */
class CreditTransaction
{
    private string $id;

    private string $userId;

    /**
     * Signed. Negative is a charge, positive is a credit. There is no
     * separate «type» column: the sign is the type, and a sum is the balance.
     */
    private int $amount;

    private CreditTransactionReason $reason;

    /**
     * The integration event that caused it, when there was one. It is what
     * makes the whole context auditable: every movement can be traced back to
     * a business fact (`RN-5`).
     */
    private ?string $eventId = null;

    /** @var array<string, scalar|null> */
    private array $metadata = [];

    private \DateTimeImmutable $occurredAt;

    /**
     * @param array<string, scalar|null> $metadata
     */
    public function __construct(
        CreditTransactionId $id,
        UserId $userId,
        int $amount,
        CreditTransactionReason $reason,
        \DateTimeImmutable $occurredAt,
        ?string $eventId = null,
        array $metadata = [],
    ) {
        $this->id = $id->value();
        $this->userId = $userId->value();
        $this->amount = $amount;
        $this->reason = $reason;
        $this->occurredAt = $occurredAt;
        $this->eventId = $eventId;
        $this->metadata = $metadata;
    }

    public function id(): CreditTransactionId
    {
        return CreditTransactionId::fromString($this->id);
    }

    public function userId(): UserId
    {
        return UserId::fromString($this->userId);
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function reason(): CreditTransactionReason
    {
        return $this->reason;
    }

    public function eventId(): ?string
    {
        return $this->eventId;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
