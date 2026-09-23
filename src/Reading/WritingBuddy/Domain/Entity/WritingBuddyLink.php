<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\WritingBuddy\Domain\Entity;

use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Reading\WritingBuddy\Domain\Enum\WritingBuddyStatus;
use LectoresBeta\Reading\WritingBuddy\Domain\ValueObject\WritingBuddyLinkId;

/**
 * A reciprocal tie between two people.
 *
 * The pair is stored **sorted**, so that (A, B) and (B, A) are the same row
 * and the unique index actually means «one live link per pair». Who proposed
 * is kept in its own column, because that is what the sorting throws away.
 *
 * What the tie enables is still open (`R-3`). It could grant access
 * automatically, which is why nothing here assumes it does.
 */
class WritingBuddyLink
{
    private string $id;

    private string $memberOne;

    private string $memberTwo;

    private string $proposedBy;

    private WritingBuddyStatus $status;

    private \DateTimeImmutable $proposedAt;

    private ?\DateTimeImmutable $resolvedAt = null;

    public function __construct(
        WritingBuddyLinkId $id,
        ReaderId $proposer,
        ReaderId $partner,
        \DateTimeImmutable $now,
    ) {
        $pair = [$proposer->value(), $partner->value()];
        sort($pair);

        $this->id = $id->value();
        $this->memberOne = $pair[0];
        $this->memberTwo = $pair[1];
        $this->proposedBy = $proposer->value();
        $this->status = WritingBuddyStatus::PROPOSED;
        $this->proposedAt = $now;
    }

    public function id(): WritingBuddyLinkId
    {
        return WritingBuddyLinkId::fromString($this->id);
    }

    public function proposedBy(): ReaderId
    {
        return ReaderId::fromString($this->proposedBy);
    }

    public function status(): WritingBuddyStatus
    {
        return $this->status;
    }

    public function involves(ReaderId $reader): bool
    {
        return \in_array($reader->value(), [$this->memberOne, $this->memberTwo], true);
    }

    public function accept(\DateTimeImmutable $now): void
    {
        $this->resolve(WritingBuddyStatus::ACCEPTED, $now);
    }

    public function decline(\DateTimeImmutable $now): void
    {
        $this->resolve(WritingBuddyStatus::DECLINED, $now);
    }

    public function end(\DateTimeImmutable $now): void
    {
        $this->status = WritingBuddyStatus::ENDED;
        $this->resolvedAt = $now;
    }

    private function resolve(WritingBuddyStatus $status, \DateTimeImmutable $now): void
    {
        if (WritingBuddyStatus::PROPOSED !== $this->status) {
            return;
        }

        $this->status = $status;
        $this->resolvedAt = $now;
    }
}
