<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Subscription\Domain\Repository;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Subscription\Domain\Entity\AuthorSubscription;

interface AuthorSubscriptionRepository
{
    public function between(MemberId $subscriberId, MemberId $authorId): ?AuthorSubscription;

    public function save(AuthorSubscription $subscription): void;

    public function remove(AuthorSubscription $subscription): void;
}
