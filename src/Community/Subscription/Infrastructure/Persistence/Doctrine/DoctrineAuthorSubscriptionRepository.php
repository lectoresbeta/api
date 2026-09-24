<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Subscription\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Subscription\Domain\Entity\AuthorSubscription;
use LectoresBeta\Community\Subscription\Domain\Repository\AuthorSubscriptionRepository;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<AuthorSubscription>
 */
final class DoctrineAuthorSubscriptionRepository extends DoctrineRepository implements AuthorSubscriptionRepository
{
    public function between(MemberId $subscriberId, MemberId $authorId): ?AuthorSubscription
    {
        return $this->repository()->findOneBy([
            'subscriberId' => $subscriberId->value(),
            'authorId' => $authorId->value(),
        ]);
    }

    public function save(AuthorSubscription $subscription): void
    {
        $this->register($subscription);
    }

    public function remove(AuthorSubscription $subscription): void
    {
        $this->forget($subscription);
    }

    protected function entityClass(): string
    {
        return AuthorSubscription::class;
    }
}
