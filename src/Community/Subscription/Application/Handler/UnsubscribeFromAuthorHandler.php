<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Subscription\Application\Handler;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Subscription\Application\Command\UnsubscribeFromAuthor;
use LectoresBeta\Community\Subscription\Domain\Event\AuthorUnsubscribed;
use LectoresBeta\Community\Subscription\Domain\Repository\AuthorSubscriptionRepository;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Dejar de seguir a un autor (`FEAT-COM-010` `RN-4`).
 *
 * **No falla nunca.** Ni si no le seguía, ni si esa persona ya no existe, ni
 * si el identificador no tiene forma de identificador: en los tres casos el
 * estado que se pedía —no seguirle— ya se cumple, y un error ahí obligaría al
 * cliente a distinguir fracasos que no lo son.
 *
 * El hecho solo se publica cuando algo cambió de verdad. Anunciar que alguien
 * dejó de seguir a quien no seguía haría trabajar a cada proyección para
 * nada.
 */
final readonly class UnsubscribeFromAuthorHandler
{
    public function __construct(
        private AuthorSubscriptionRepository $subscriptions,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(UnsubscribeFromAuthor $command): void
    {
        try {
            $subscriberId = MemberId::fromString($command->subscriberId);
            $authorId = MemberId::fromString($command->authorId);
        } catch (InvalidValue) {
            return;
        }

        $subscription = $this->subscriptions->between($subscriberId, $authorId);

        if (null === $subscription) {
            return;
        }

        $this->session->execute(function () use ($subscription): void {
            $this->subscriptions->remove($subscription);
        });

        $this->events->publish(new AuthorUnsubscribed(
            EventId::generate(),
            $subscriberId,
            $authorId,
            $this->clock->now(),
        ));
    }
}
