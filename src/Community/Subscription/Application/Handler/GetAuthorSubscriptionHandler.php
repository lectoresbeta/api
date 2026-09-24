<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Subscription\Application\Handler;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Subscription\Application\DTO\SubscriptionState;
use LectoresBeta\Community\Subscription\Application\Query\GetAuthorSubscription;
use LectoresBeta\Community\Subscription\Domain\Repository\AuthorSubscriptionRepository;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;

/**
 * ¿Sigo a esta persona? (`FEAT-COM-010`).
 *
 * Responde `false` para un identificador que no existe en vez de `404`: la
 * pregunta es sobre **mi** relación con él, y la respuesta —no le sigo— es
 * cierta. Un `404` aquí contaría además si esa cuenta existe, a cualquiera
 * que pruebe identificadores.
 */
final readonly class GetAuthorSubscriptionHandler
{
    public function __construct(private AuthorSubscriptionRepository $subscriptions)
    {
    }

    public function __invoke(GetAuthorSubscription $query): SubscriptionState
    {
        try {
            $subscriberId = MemberId::fromString($query->subscriberId);
            $authorId = MemberId::fromString($query->authorId);
        } catch (InvalidValue) {
            return new SubscriptionState(false, null);
        }

        $subscription = $this->subscriptions->between($subscriberId, $authorId);

        return new SubscriptionState(null !== $subscription, $subscription?->createdAt());
    }
}
