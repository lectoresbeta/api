<?php

declare(strict_types=1);

namespace LectoresBeta\User\Privacy\Application\Handler;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Privacy\Application\Event\AuthorUnsubscribed;
use LectoresBeta\User\Privacy\Domain\Repository\AuthorFollowerRepository;

/**
 * Retirar un seguidor de la copia local (`FEAT-COM-010`).
 *
 * Idempotente por la misma razón que su hermano, y por una más: si la fila ya
 * no está, el estado que el hecho afirma ya se cumple.
 *
 * **No mira la fecha.** Podría llegar un `AuthorSubscribed` posterior —volver
 * a seguir— antes que este; el orden no está garantizado. Se acepta a
 * sabiendas: el caso es raro y el error dura hasta el siguiente hecho, y la
 * alternativa —guardar la fecha del último cambio por par— es un modelo más
 * grande para una copia que solo responde «sí o no».
 */
final readonly class ForgetFollower
{
    public function __construct(
        private AuthorFollowerRepository $followers,
        private TransactionalSession $session,
    ) {
    }

    public function __invoke(AuthorUnsubscribed $event): void
    {
        try {
            $authorId = UserId::fromString($event->authorId);
            $followerId = UserId::fromString($event->subscriberId);
        } catch (InvalidValue) {
            return;
        }

        $follower = $this->followers->between($followerId, $authorId);

        if (null === $follower) {
            return;
        }

        $this->session->execute(function () use ($follower): void {
            $this->followers->remove($follower);
        });
    }
}
