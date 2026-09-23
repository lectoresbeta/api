<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Subscription\Domain\Entity;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Subscription\Domain\ValueObject\AuthorSubscriptionId;

/**
 * Following an author (`FEAT-COM-016`). One per pair, and nobody follows
 * themselves (`RN-2`).
 *
 * Following is not friendship: the relation is asymmetric, whatever the
 * design calls it (`P-8`).
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
        if ($subscriberId->value() === $authorId->value()) {
            throw new \DomainException('Nobody subscribes to themselves.');
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
}
