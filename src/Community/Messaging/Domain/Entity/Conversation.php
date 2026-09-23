<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Messaging\Domain\Entity;

use LectoresBeta\Community\Messaging\Domain\ValueObject\ConversationId;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;

/**
 * A thread between two people.
 *
 * The pair is stored sorted so that (A,B) and (B,A) are the same row and the
 * unique index means one conversation per pair.
 *
 * Whether the recipient accepts direct messages is a `User` setting
 * (`FEAT-USR-038`) that this context consumes; it is checked when sending,
 * not stored here.
 */
class Conversation
{
    private string $id;

    private string $memberOne;

    private string $memberTwo;

    private \DateTimeImmutable $startedAt;

    private \DateTimeImmutable $lastMessageAt;

    public function __construct(
        ConversationId $id,
        MemberId $one,
        MemberId $two,
        \DateTimeImmutable $now,
    ) {
        $pair = [$one->value(), $two->value()];
        sort($pair);

        $this->id = $id->value();
        $this->memberOne = $pair[0];
        $this->memberTwo = $pair[1];
        $this->startedAt = $now;
        $this->lastMessageAt = $now;
    }

    public function id(): ConversationId
    {
        return ConversationId::fromString($this->id);
    }

    public function includes(MemberId $member): bool
    {
        return \in_array($member->value(), [$this->memberOne, $this->memberTwo], true);
    }

    public function messageSent(\DateTimeImmutable $now): void
    {
        $this->lastMessageAt = $now;
    }
}
