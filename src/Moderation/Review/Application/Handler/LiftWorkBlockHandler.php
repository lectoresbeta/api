<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Review\Application\Handler;

use LectoresBeta\Moderation\AuditLog\Application\Service\RecordAuditEntry;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Moderation\Review\Application\Command\LiftWorkBlock;
use LectoresBeta\Moderation\Review\Domain\Event\ModerationBlockLifted;
use LectoresBeta\Moderation\Review\Domain\Exception\ClaimReviewRefused;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Levantar un bloqueo (`FEAT-MOD-003` `RN-7`).
 *
 * El bloqueo es **indefinido y solo lo revoca un moderador**. Hasta ahora era
 * definitivo de hecho, no solo de derecho: el modelo admitía deshacerlo y no
 * había por dónde pedirlo, así que un error no tenía arreglo.
 *
 * Lleva **motivación escrita**, como la decisión que lo impuso. Deshacer una
 * decisión necesita explicarse todavía más que tomarla, y el registro de
 * auditoría es donde esa explicación vive.
 *
 * `Moderation` **no toca el estado de la obra**: publica que el bloqueo se ha
 * levantado y `Work` lo aplica, igual que al bloquear. Si lo escribiera aquí,
 * dos contextos gobernarían el mismo dato.
 */
final readonly class LiftWorkBlockHandler
{
    private const TARGETS = ['WORK', 'CHAPTER'];

    public function __construct(
        private RecordAuditEntry $audit,
        private EventPublisher $events,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(LiftWorkBlock $command): void
    {
        $targetType = strtoupper(trim($command->targetType));

        if (!\in_array($targetType, self::TARGETS, true)) {
            throw ClaimReviewRefused::becauseItDoesNotExist();
        }

        $motivation = trim($command->motivation);

        if ('' === $motivation) {
            throw ClaimReviewRefused::becauseThereIsNoMotivation();
        }

        try {
            $moderatorId = PartyId::fromString($command->moderatorId);
        } catch (InvalidValue) {
            throw ClaimReviewRefused::becauseTheModeratorIsAParty();
        }

        $now = $this->clock->now();

        $this->session->execute(function () use ($moderatorId, $targetType, $command, $motivation): void {
            $this->audit->of(
                $moderatorId,
                'MODERATION_BLOCK_LIFTED',
                $targetType,
                $command->targetId,
                $motivation,
            );
        });

        $this->events->publish(new ModerationBlockLifted(
            EventId::generate(),
            $targetType,
            $command->targetId,
            $now,
        ));
    }
}
