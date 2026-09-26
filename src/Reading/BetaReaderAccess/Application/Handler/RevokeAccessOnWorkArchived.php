<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderAccess\Application\Handler;

use LectoresBeta\Reading\BetaReaderAccess\Application\Event\WorkArchived;
use LectoresBeta\Reading\BetaReaderAccess\Domain\Event\BetaReaderAccessRevoked;
use LectoresBeta\Reading\BetaReaderAccess\Domain\Repository\BetaReaderAccessRepository;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * El autor ha retirado la obra, así que no hay nada que leer (`FEAT-WRK-006`
 * `RN-6`).
 *
 * Se revocan **todos** los accesos vivos a esa obra, vinieran de donde
 * vinieran. Es el mismo trabajo que hace un bloqueo entre personas y por la
 * misma razón: **quién decide qué es un acceso y cuándo deja de valer es este
 * contexto**, no quien publica el hecho.
 *
 * Lo ya entregado no se toca: el autor lo pagó y el lector lo ganó. Quien
 * estuviera corrigiendo pierde ese trabajo, que es el coste —dicho de
 * frente— de que un autor pueda retirar lo suyo.
 *
 * Idempotente sin registro: revocar un acceso ya revocado no lo cambia, y
 * solo se anuncia lo que de verdad estaba vivo.
 */
final readonly class RevokeAccessOnWorkArchived
{
    public function __construct(
        private BetaReaderAccessRepository $accesses,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(WorkArchived $event): void
    {
        try {
            $workId = WorkId::fromString($event->workId);
        } catch (InvalidValue) {
            return;
        }

        $revoked = $this->accesses->liveOnWork($workId);

        if ([] === $revoked) {
            return;
        }

        $now = $this->clock->now();

        $this->session->execute(function () use ($revoked, $now): void {
            foreach ($revoked as $access) {
                $access->revoke($now);
                $this->accesses->save($access);
            }
        });

        foreach ($revoked as $access) {
            $this->events->publish(new BetaReaderAccessRevoked(
                EventId::generate(),
                $access->id(),
                $access->workId(),
                $access->readerId(),
                $now,
            ));
        }
    }
}
