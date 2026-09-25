<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Sanction\Domain\Event;

use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Moderation\Sanction\Domain\Enum\SanctionType;
use LectoresBeta\Moderation\Sanction\Domain\ValueObject\SanctionId;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * Un moderador ha sancionado a alguien (`FEAT-MOD-006`).
 *
 * **`Moderation` registra la sanción; `User` la aplica.** Si `Moderation`
 * marcara la cuenta directamente habría dos dueños del estado del usuario, y
 * el día que discreparan no habría forma de saber cuál manda.
 *
 * Lleva el **motivo**, y no por adorno: al usuario se le comunica tipo,
 * motivo y duración (`RN-4`). Una sanción que no se entiende no corrige nada,
 * solo hace que la persona se vaya.
 */
final readonly class SanctionImposed implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private SanctionId $sanctionId,
        private PartyId $userId,
        private SanctionType $type,
        private string $reason,
        private ?\DateTimeImmutable $expiresAt,
        private \DateTimeImmutable $imposedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'SanctionImposed';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->imposedAt;
    }

    public function payload(): array
    {
        return [
            'sanctionId' => $this->sanctionId->value(),
            'userId' => $this->userId->value(),
            'type' => $this->type->value,
            'reason' => $this->reason,
            'expiresAt' => $this->expiresAt?->format(\DATE_ATOM),
            'imposedAt' => $this->imposedAt->format(\DATE_ATOM),
        ];
    }
}
