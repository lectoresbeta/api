<?php

declare(strict_types=1);

namespace LectoresBeta\User\Privacy\Application\Handler;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Privacy\Application\Event\UserBlocked;
use LectoresBeta\User\Privacy\Domain\Entity\BlockedPair;
use LectoresBeta\User\Privacy\Domain\Repository\BlockedPairRepository;

/**
 * Apuntar un bloqueo en la copia local (`FEAT-COM-034`).
 *
 * Idempotente sin esfuerzo: la fila es el par ordenado, así que reprocesar el
 * mismo hecho —o recibir el bloqueo contrario, que para esta pregunta es lo
 * mismo— escribe la misma fila.
 */
final readonly class RememberBlock
{
    public function __construct(
        private BlockedPairRepository $blocks,
        private TransactionalSession $session,
    ) {
    }

    public function __invoke(UserBlocked $event): void
    {
        try {
            $one = UserId::fromString($event->blockerId);
            $other = UserId::fromString($event->blockedId);
        } catch (InvalidValue) {
            return;
        }

        if ($this->blocks->exists($one, $other)) {
            return;
        }

        $pair = new BlockedPair($one, $other, $event->occurredAt());

        $this->session->execute(function () use ($pair): void {
            $this->blocks->save($pair);
        });
    }
}
