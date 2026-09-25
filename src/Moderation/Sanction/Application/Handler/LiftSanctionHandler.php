<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Sanction\Application\Handler;

use LectoresBeta\Moderation\AuditLog\Application\Service\RecordAuditEntry;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Moderation\Sanction\Application\Command\LiftSanction;
use LectoresBeta\Moderation\Sanction\Domain\Event\SanctionLifted;
use LectoresBeta\Moderation\Sanction\Domain\Exception\SanctionRefused;
use LectoresBeta\Moderation\Sanction\Domain\Repository\SanctionRepository;
use LectoresBeta\Moderation\Sanction\Domain\ValueObject\SanctionId;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Levantar una sanción antes de tiempo (`FEAT-MOD-006` `RN-3`).
 *
 * **Cualquiera se puede levantar, incluida la expulsión** (`RN-7`). Al no
 * anonimizar, la expulsión no destruye nada que impida volver atrás, y esa es
 * la otra mitad de por qué conserva los datos.
 *
 * Levantar lo ya levantado **sí es un error aquí**, al revés que en otras
 * operaciones idempotentes del proyecto: es un acto administrativo que se
 * registra y se comunica, y hacerlo dos veces dejaría en el historial algo
 * que no ocurrió.
 */
final readonly class LiftSanctionHandler
{
    public function __construct(
        private SanctionRepository $sanctions,
        private RecordAuditEntry $audit,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(LiftSanction $command): void
    {
        try {
            $sanction = $this->sanctions->ofId(SanctionId::fromString($command->sanctionId));
        } catch (InvalidValue) {
            throw SanctionRefused::notFound();
        }

        if (null === $sanction) {
            throw SanctionRefused::notFound();
        }

        $now = $this->clock->now();

        if (!$sanction->isInForceAt($now)) {
            throw SanctionRefused::notInForce();
        }

        $reason = trim($command->reason ?? '');

        if ('' === $reason) {
            throw SanctionRefused::reasonMissing();
        }

        $this->session->execute(function () use ($sanction, $command, $reason, $now): void {
            $sanction->lift($now);
            $this->sanctions->save($sanction);

            $this->audit->of(
                PartyId::fromString($command->moderatorId),
                'SANCTION_LIFTED',
                'USER',
                $sanction->userId()->value(),
                $reason,
                ['sanctionId' => $sanction->id()->value()],
            );
        });

        $this->events->publish(new SanctionLifted(
            EventId::generate(),
            $sanction->id(),
            $sanction->userId(),
            $reason,
            $now,
        ));
    }
}
