<?php

declare(strict_types=1);

namespace LectoresBeta\User\Privacy\Application\Handler;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Privacy\Application\Event\UserUnblocked;
use LectoresBeta\User\Privacy\Domain\Repository\BlockedPairRepository;

/**
 * Retirar un bloqueo de la copia local (`FEAT-COM-034`).
 *
 * **Cuidado con lo que esta clase no sabe**: si las dos personas se
 * bloquearon mutuamente, levantar uno de los dos deja la fila sin bloqueo
 * aunque el otro siga vivo. Es el precio de guardar el par sin dirección, y
 * se asume: el caso es raro, el efecto dura hasta el siguiente hecho y la
 * alternativa —copiar aquí quién bloqueó a quién— duplicaría en `User` un
 * modelo que es de `Community`.
 */
final readonly class ForgetBlock
{
    public function __construct(
        private BlockedPairRepository $blocks,
        private TransactionalSession $session,
    ) {
    }

    public function __invoke(UserUnblocked $event): void
    {
        try {
            $one = UserId::fromString($event->blockerId);
            $other = UserId::fromString($event->blockedId);
        } catch (InvalidValue) {
            return;
        }

        $pair = $this->blocks->between($one, $other);

        if (null === $pair) {
            return;
        }

        $this->session->execute(function () use ($pair): void {
            $this->blocks->remove($pair);
        });
    }
}
