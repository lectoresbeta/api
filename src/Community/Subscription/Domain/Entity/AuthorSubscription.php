<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Subscription\Domain\Entity;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Subscription\Domain\Exception\SubscriptionRefused;
use LectoresBeta\Community\Subscription\Domain\ValueObject\AuthorSubscriptionId;

/**
 * Following an author (`FEAT-COM-010`). One per pair, and nobody follows
 * themselves (`RN-2`).
 *
 * Following is not friendship: the relation is asymmetric, whatever the
 * design calls it (`P-8`).
 *
 * It grants **nothing** on its own (`RN-7`): no work opens, no beta reader
 * access appears, no conversation starts. What changes is which notices
 * somebody gets and which audiences include them.
 *
 * There is no history. Following again after unfollowing is a new row with a
 * new date, and that is the whole record.
 */
class AuthorSubscription
{
    private string $id;

    private string $subscriberId;

    private string $authorId;

    private \DateTimeImmutable $createdAt;

    public function __construct(
        AuthorSubscriptionId $id,
        MemberId $subscriberId,
        MemberId $authorId,
        \DateTimeImmutable $now,
    ) {
        // Una regla de negocio, no una comprobación defensiva: quien la
        // incumple recibe un `422` con su código, no un `500`.
        if ($subscriberId->value() === $authorId->value()) {
            throw SubscriptionRefused::yourself();
        }

        $this->id = $id->value();
        $this->subscriberId = $subscriberId->value();
        $this->authorId = $authorId->value();
        $this->createdAt = $now;
    }

    public function id(): AuthorSubscriptionId
    {
        return AuthorSubscriptionId::fromString($this->id);
    }

    public function subscriberId(): MemberId
    {
        return MemberId::fromString($this->subscriberId);
    }

    public function authorId(): MemberId
    {
        return MemberId::fromString($this->authorId);
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
