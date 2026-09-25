<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Sanction\Application\Handler;

use LectoresBeta\Moderation\AuditLog\Application\Service\RecordAuditEntry;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\ClaimId;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Moderation\Sanction\Application\Command\ImposeSanction;
use LectoresBeta\Moderation\Sanction\Domain\Entity\Sanction;
use LectoresBeta\Moderation\Sanction\Domain\Enum\SanctionType;
use LectoresBeta\Moderation\Sanction\Domain\Enum\SuspensionDuration;
use LectoresBeta\Moderation\Sanction\Domain\Event\SanctionImposed;
use LectoresBeta\Moderation\Sanction\Domain\Exception\SanctionRefused;
use LectoresBeta\Moderation\Sanction\Domain\Repository\SanctionRepository;
use LectoresBeta\Moderation\Sanction\Domain\ValueObject\SanctionId;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Application\Contract\RegisteredUsers;

/**
 * Sancionar a alguien (`FEAT-MOD-006`).
 *
 * **`Moderation` registra; `User` aplica.** Este contexto no toca la cuenta
 * de nadie: publica el hecho y `User` decide qué significa en su modelo. Si
 * marcara la cuenta directamente habría dos dueños del estado del usuario.
 *
 * La sanción **no mueve créditos** (`RN-6`), y esa separación es cómoda de
 * perder: devolver el crédito repara al perjudicado, la sanción corrige al
 * infractor, y una reclamación puede producir lo primero sin lo segundo y al
 * revés.
 *
 * Queda en el registro de auditoría con la identidad de quien la impuso. Un
 * poder que se ejerce sin dejar nombre es un poder que nadie puede revisar
 * después.
 */
final readonly class ImposeSanctionHandler
{
    public function __construct(
        private SanctionRepository $sanctions,
        private RegisteredUsers $users,
        private RecordAuditEntry $audit,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(ImposeSanction $command): string
    {
        $type = SanctionType::tryFrom(strtoupper($command->type ?? ''))
            ?? throw SanctionRefused::unknownType();

        $reason = trim($command->reason ?? '');

        if ('' === $reason) {
            throw SanctionRefused::reasonMissing();
        }

        $duration = $this->duration($type, $command->duration);

        try {
            $userId = PartyId::fromString($command->userId);
        } catch (InvalidValue) {
            throw SanctionRefused::userNotFound();
        }

        if (!$this->users->exists($userId->value())) {
            throw SanctionRefused::userNotFound();
        }

        $now = $this->clock->now();

        $sanction = new Sanction(
            SanctionId::generate(),
            $userId,
            $type,
            $reason,
            PartyId::fromString($command->moderatorId),
            $now,
            $duration,
            null === $command->claimId || '' === $command->claimId ? null : ClaimId::fromString($command->claimId),
        );

        $this->session->execute(function () use ($sanction, $command, $type, $reason, $userId): void {
            $this->sanctions->save($sanction);

            $this->audit->of(
                PartyId::fromString($command->moderatorId),
                'SANCTION_IMPOSED',
                'USER',
                $userId->value(),
                $reason,
                ['type' => $type->value, 'sanctionId' => $sanction->id()->value()],
            );
        });

        $this->events->publish(new SanctionImposed(
            EventId::generate(),
            $sanction->id(),
            $userId,
            $type,
            $reason,
            $duration?->endingFrom($now),
            $now,
        ));

        return $sanction->id()->value();
    }

    /**
     * Una suspensión parcial **siempre lleva plazo**: sin él sería una total
     * con otro nombre, y la total es una decisión distinta que se toma a
     * conciencia. Las demás no lo admiten, para que nadie ponga una fecha a
     * una expulsión y crea que caduca.
     */
    private function duration(SanctionType $type, ?string $duration): ?SuspensionDuration
    {
        if (!$type->isTemporary()) {
            return null;
        }

        if (null === $duration || '' === $duration) {
            throw SanctionRefused::durationMissing();
        }

        return SuspensionDuration::tryFrom(strtoupper($duration)) ?? throw SanctionRefused::unknownDuration();
    }
}
