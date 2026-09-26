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
 * Ocurre por tres caminos: **echarse atrás** —descartar el borrador de una
 * corrección que había concedido el acceso y nunca entregó nada
 * (`FEAT-RDG-001` `RN-5`)—, **un bloqueo** (`FEAT-COM-034` `RN-B1`) y **la
 * decisión del autor** (`FEAT-RDG-010`).
 *
 * Los tres publican **este mismo hecho**, y quien lo consume no tiene por qué
 * saber cuál de ellos fue: lo que le importa es que esa persona ya no puede
 * leer esa obra.
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
