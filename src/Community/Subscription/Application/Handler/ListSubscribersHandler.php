<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Subscription\Application\Handler;

use LectoresBeta\Community\Subscription\Application\DTO\SubscriptionPage;
use LectoresBeta\Community\Subscription\Application\Query\ListSubscribers;
use LectoresBeta\Community\Subscription\Application\Service\PageOfPeople;
use LectoresBeta\Community\Subscription\Domain\Entity\AuthorSubscription;
use LectoresBeta\Community\Subscription\Domain\Repository\AuthorSubscriptionRepository;

/**
 * Quién sigue a esta persona (`FEAT-COM-027`).
 *
 * La misma tabla que su hermano, leída por el otro extremo: aquí se enseña a
 * quien sigue.
 */
final readonly class ListSubscribersHandler
{
    public function __construct(
        private AuthorSubscriptionRepository $subscriptions,
        private PageOfPeople $page,
    ) {
    }

    public function __invoke(ListSubscribers $query): SubscriptionPage
    {
        $owner = $this->page->owner($query->userId, $query->viewerId);
        $limit = $this->page->size($query->limit);

        return $this->page->of(
            $this->subscriptions->subscribersOf($owner, $this->page->after($query->cursor), $limit),
            $limit,
            $query->viewerId,
            static fn (AuthorSubscription $subscription): string => $subscription->subscriberId()->value(),
        );
    }
}
