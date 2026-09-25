<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Sanction\Domain\Event;

use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Moderation\Sanction\Domain\ValueObject\SanctionId;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * Una sanción se ha levantado antes de tiempo (`FEAT-MOD-006` `RN-3`).
 *
 * **No se publica cuando una caduca sola**, y es deliberado: las de plazo
 * fijo terminan por la fecha que `User` ya conoce, y publicar un hecho por
 * cada caducidad exigiría un proceso que recorriera la tabla buscando
 * vencimientos — un temporizador que puede no ejecutarse, para decir algo que
 * ya se sabía.
 *
 * Lo que sí hace falta anunciar es la decisión de alguien: levantar una
 * suspensión total o readmitir a quien fue expulsado. Esas no caducan.
 */
final readonly class SanctionLifted implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private SanctionId $sanctionId,
        private PartyId $userId,
        private string $reason,
        private \DateTimeImmutable $liftedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'SanctionLifted';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->liftedAt;
    }

    public function payload(): array
    {
        return [
            'sanctionId' => $this->sanctionId->value(),
            'userId' => $this->userId->value(),
            'reason' => $this->reason,
            'liftedAt' => $this->liftedAt->format(\DATE_ATOM),
        ];
    }
}
