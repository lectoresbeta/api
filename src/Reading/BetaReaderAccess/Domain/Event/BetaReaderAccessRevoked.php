<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderAccess\Domain\Event;

use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\BetaReaderAccessId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * Alguien deja de ser lector beta de una obra.
 *
 * Hoy solo ocurre por echarse atrás: descartar un borrador de una corrección
 * que había concedido el acceso y nunca llegó a entregar nada
 * (`FEAT-RDG-001` `RN-5`). El día que el autor pueda revocar a mano
 * (`FEAT-RDG-010`), será el mismo hecho.
 */
final readonly class BetaReaderAccessRevoked implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private BetaReaderAccessId $accessId,
        private WorkId $workId,
        private ReaderId $readerId,
        private \DateTimeImmutable $revokedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'BetaReaderAccessRevoked';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function payload(): array
    {
        return [
            'accessId' => $this->accessId->value(),
            'workId' => $this->workId->value(),
            'readerId' => $this->readerId->value(),
            'revokedAt' => $this->revokedAt->format(\DATE_ATOM),
        ];
    }
}
