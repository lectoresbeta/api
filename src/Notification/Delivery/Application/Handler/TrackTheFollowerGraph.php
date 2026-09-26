<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Handler;

use LectoresBeta\Notification\Delivery\Application\Event\AuthorSubscribed;
use LectoresBeta\Notification\Delivery\Application\Event\AuthorUnsubscribed;
use LectoresBeta\Notification\Delivery\Domain\Entity\AuthorFollower;
use LectoresBeta\Notification\Delivery\Domain\Repository\AuthorFollowerRepository;
use LectoresBeta\Notification\Delivery\Domain\ValueObject\RecipientId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Mantener la copia del grafo con la que se reparten los avisos
 * (`FEAT-NOT-004`).
 *
 * **Las dos mitades en una clase**, porque son un mismo trabajo visto por sus
 * dos extremos y separarlas invita a implementar solo la primera. Es lo que
 * de verdad hace falta vigilar: una copia que solo sabe apuntar envejece
 * hacia el lado malo —avisos que no se apagan al dejar de seguir— y eso no se
 * nota hasta que alguien se queja.
 *
 * **Sin control de duplicados, y no es un olvido**: la fila es el par, así que
 * volver a procesar el mismo hecho escribe lo mismo. Aquí no hay efecto que
 * acumular, hay un estado que afirmar.
 */
final readonly class TrackTheFollowerGraph
{
    public function __construct(
        private AuthorFollowerRepository $followers,
        private TransactionalSession $session,
    ) {
    }

    public function subscribed(AuthorSubscribed $event): void
    {
        $pair = $this->pairOf($event->subscriberId, $event->authorId);

        if (null === $pair) {
            return;
        }

        [$followerId, $authorId] = $pair;
        $existing = $this->followers->between($followerId, $authorId);

        if (null !== $existing) {
            $existing->keepEarliest($event->occurredAt());
        }

        $follower = $existing ?? new AuthorFollower($authorId, $followerId, $event->occurredAt());

        $this->session->execute(function () use ($follower): void {
            $this->followers->save($follower);
        });
    }

    public function unsubscribed(AuthorUnsubscribed $event): void
    {
        $pair = $this->pairOf($event->subscriberId, $event->authorId);

        if (null === $pair) {
            return;
        }

        $existing = $this->followers->between(...$pair);

        if (null === $existing) {
            return;
        }

        $this->session->execute(function () use ($existing): void {
            $this->followers->remove($existing);
        });
    }

    /**
     * @return array{0: RecipientId, 1: RecipientId}|null `[seguidor, autor]`
     */
    private function pairOf(string $subscriberId, string $authorId): ?array
    {
        try {
            // Un hecho con un identificador ilegible se descarta, no se
            // reintenta: reintentarlo lo dejaría dando vueltas para siempre.
            return [RecipientId::fromString($subscriberId), RecipientId::fromString($authorId)];
        } catch (InvalidValue) {
            return null;
        }
    }
}
