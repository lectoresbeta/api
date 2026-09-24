<?php

declare(strict_types=1);

namespace LectoresBeta\User\Privacy\Application\Handler;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Privacy\Application\Event\AuthorSubscribed;
use LectoresBeta\User\Privacy\Domain\Entity\AuthorFollower;
use LectoresBeta\User\Privacy\Domain\Repository\AuthorFollowerRepository;

/**
 * Apuntar un seguidor en la copia local del grafo (`FEAT-COM-010`).
 *
 * **Sin control de duplicados, y no es un olvido**: la fila es el par, así
 * que volver a procesar el mismo hecho escribe lo mismo. Un registro de
 * eventos procesados como el de `Credits` protege de *aplicar dos veces un
 * efecto*, y aquí no hay efecto que acumular — hay un estado que afirmar.
 *
 * `User` no comprueba que las dos cuentas existan. El hecho viene de quien es
 * dueño de esa decisión, y desconfiar aquí significaría rehacer su trabajo con
 * información más vieja.
 */
final readonly class RememberFollower
{
    public function __construct(
        private AuthorFollowerRepository $followers,
        private TransactionalSession $session,
    ) {
    }

    public function __invoke(AuthorSubscribed $event): void
    {
        try {
            $authorId = UserId::fromString($event->authorId);
            $followerId = UserId::fromString($event->subscriberId);
        } catch (InvalidValue) {
            // Un hecho con un identificador ilegible no se reintenta: se
            // descarta. Reintentarlo lo dejaría dando vueltas para siempre.
            return;
        }

        $existing = $this->followers->between($followerId, $authorId);

        if (null !== $existing) {
            $existing->keepEarliest($event->occurredAt());
        }

        $follower = $existing ?? new AuthorFollower($authorId, $followerId, $event->occurredAt());

        $this->session->execute(function () use ($follower): void {
            $this->followers->save($follower);
        });
    }
}
