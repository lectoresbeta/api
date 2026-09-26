<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Subscription\Application\Handler;

use LectoresBeta\Community\Subscription\Application\DTO\SubscriptionPage;
use LectoresBeta\Community\Subscription\Application\Query\ListAuthorSubscriptions;
use LectoresBeta\Community\Subscription\Application\Service\PageOfPeople;
use LectoresBeta\Community\Subscription\Domain\Entity\AuthorSubscription;
use LectoresBeta\Community\Subscription\Domain\Repository\AuthorSubscriptionRepository;

/**
 * A quién sigue esta persona (`FEAT-COM-027`).
 *
 * De la suscripción se enseña **el otro extremo**: aquí, el autor seguido.
 */
final readonly class ListAuthorSubscriptionsHandler
{
    public function __construct(
        private AuthorSubscriptionRepository $subscriptions,
        private PageOfPeople $page,
    ) {
    }

    public function __invoke(ListAuthorSubscriptions $query): SubscriptionPage
    {
        $owner = $this->page->owner($query->userId, $query->viewerId);
        $limit = $this->page->size($query->limit);

        return $this->page->of(
            $this->subscriptions->subscriptionsOf($owner, $this->page->after($query->cursor), $limit),
            $limit,
            $query->viewerId,
            static fn (AuthorSubscription $subscription): string => $subscription->authorId()->value(),
        );
    }
}
