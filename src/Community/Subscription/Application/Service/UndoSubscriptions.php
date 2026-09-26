<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Subscription\Application\Service;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Subscription\Domain\Event\AuthorUnsubscribed;
use LectoresBeta\Community\Subscription\Domain\Repository\AuthorSubscriptionRepository;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Deshacer los dos seguimientos entre dos personas (`FEAT-COM-034` `RN-3`).
 *
 * Vive en `Subscription` y no en `Relationship` porque el seguimiento es
 * suyo: bloquear **pide** que se deshaga, no lo hace por su cuenta. Es la
 * misma frontera que separa `Community` de los demás contextos, aplicada un
 * piso más abajo.
 *
 * Publica `AuthorUnsubscribed` por cada uno, y ahí está la gracia: quien
 * proyecta el grafo de seguidores se entera de lo que le importa —que esa
 * relación ya no está— **sin aprender que detrás había un bloqueo**, que es
 * información de otro orden y no le hace falta.
 */
final readonly class UndoSubscriptions
{
    public function __construct(
        private AuthorSubscriptionRepository $subscriptions,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function betweenThem(MemberId $one, MemberId $other): void
    {
        $undone = array_filter([
            $this->subscriptions->between($one, $other),
            $this->subscriptions->between($other, $one),
        ]);

        if ([] === $undone) {
            return;
        }

        $this->session->execute(function () use ($undone): void {
            foreach ($undone as $subscription) {
                $this->subscriptions->remove($subscription);
            }
        });

        $now = $this->clock->now();

        foreach ($undone as $subscription) {
            $this->events->publish(new AuthorUnsubscribed(
                EventId::generate(),
                $subscription->subscriberId(),
                $subscription->authorId(),
                $now,
            ));
        }
    }
}
