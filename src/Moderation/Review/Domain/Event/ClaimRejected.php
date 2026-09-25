<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Review\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * Una reclamación se ha desestimado (`FEAT-MOD-002`).
 *
 * Lleva a quién avisar y nada más. **Sin la motivación**: es material interno
 * del expediente, y puede ser dura.
 *
 * Desestimar tiene consecuencias para quien reclamó —el bloqueo acumulativo
 * de [`FEAT-MOD-001`](../../../../../docs/features/moderation/FEAT-MOD-001-submit-claim.md)
 * `RN-6b`— y esas las aplica `Moderation` en su propio modelo, no quien
 * consume el hecho.
 */
final readonly class ClaimRejected implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private string $claimId,
        private string $reporterId,
        private \DateTimeImmutable $rejectedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'ClaimRejected';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->rejectedAt;
    }

    public function payload(): array
    {
        return [
            'claimId' => $this->claimId,
            'reporterId' => $this->reporterId,
            'rejectedAt' => $this->rejectedAt->format(\DATE_ATOM),
        ];
    }
}
