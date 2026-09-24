<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Subscription\Application\Service;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Subscription\Application\Contract\SubscriptionCount;
use LectoresBeta\Community\Subscription\Application\Contract\SubscriptionCounts;
use LectoresBeta\Community\Subscription\Domain\Repository\AuthorSubscriptionRepository;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;

final readonly class CountSubscriptions implements SubscriptionCounts
{
    public function __construct(private AuthorSubscriptionRepository $subscriptions)
    {
    }

    public function of(string $userId): SubscriptionCount
    {
        try {
            $member = MemberId::fromString($userId);
        } catch (InvalidValue) {
            // Quien no es nadie no sigue a nadie. Cero y no una excepción:
            // esto alimenta un contador, y un contador no es el sitio donde
            // enterarse de que un identificador está mal.
            return new SubscriptionCount(0, 0);
        }

        return new SubscriptionCount(
            $this->subscriptions->countSubscriptionsOf($member),
            $this->subscriptions->countSubscribersOf($member),
        );
    }
}
