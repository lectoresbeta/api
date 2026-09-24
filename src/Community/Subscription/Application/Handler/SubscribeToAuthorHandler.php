<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Subscription\Application\Handler;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Subscription\Application\Command\SubscribeToAuthor;
use LectoresBeta\Community\Subscription\Domain\Entity\AuthorSubscription;
use LectoresBeta\Community\Subscription\Domain\Event\AuthorSubscribed;
use LectoresBeta\Community\Subscription\Domain\Exception\SubscriptionRefused;
use LectoresBeta\Community\Subscription\Domain\Repository\AuthorSubscriptionRepository;
use LectoresBeta\Community\Subscription\Domain\ValueObject\AuthorSubscriptionId;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Application\Contract\RegisteredUsers;

/**
 * Seguir a un autor (`FEAT-COM-010`).
 *
 * **Idempotente** (`RN-1`): seguir a quien ya se sigue no crea una segunda
 * suscripción, no publica un segundo hecho y responde lo mismo. Lo que el
 * cliente pedía —seguirle— ya se cumple, y contestar `409` sería decirle que
 * ha fallado cuando no.
 *
 * Que la cuenta exista se pregunta por el **contrato publicado** de `User`
 * (`RN-3`), nunca leyendo sus tablas. Sin esa comprobación, un identificador
 * inventado crearía una suscripción a nadie, y `User` proyectaría un seguidor
 * de un autor inexistente.
 */
final readonly class SubscribeToAuthorHandler
{
    public function __construct(
        private AuthorSubscriptionRepository $subscriptions,
        private RegisteredUsers $users,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(SubscribeToAuthor $command): void
    {
        try {
            $subscriberId = MemberId::fromString($command->subscriberId);
            $authorId = MemberId::fromString($command->authorId);
        } catch (InvalidValue) {
            // Un identificador que ni siquiera tiene forma de identificador
            // responde lo mismo que uno que no existe: por fuera son el mismo
            // caso, y distinguirlos solo diría cómo se guardan aquí.
            throw SubscriptionRefused::authorNotFound();
        }

        if ($subscriberId->value() === $authorId->value()) {
            throw SubscriptionRefused::yourself();
        }

        if (!$this->users->exists($authorId->value())) {
            throw SubscriptionRefused::authorNotFound();
        }

        if (null !== $this->subscriptions->between($subscriberId, $authorId)) {
            return;
        }

        $now = $this->clock->now();

        $this->session->execute(function () use ($subscriberId, $authorId, $now): void {
            $this->subscriptions->save(new AuthorSubscription(
                AuthorSubscriptionId::generate(),
                $subscriberId,
                $authorId,
                $now,
            ));
        });

        $this->events->publish(new AuthorSubscribed(
            EventId::generate(),
            $subscriberId,
            $authorId,
            $now,
        ));
    }
}
