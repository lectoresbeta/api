<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderAccess\Domain\Event;

use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\AuthorId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\BetaReaderAccessId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * Alguien es lector beta de una obra (`FEAT-RDG-001`).
 *
 * **No lleva importes y `Credits` no lo consume.** Lo hacía cuando conceder
 * un acceso retenía créditos del autor; [`decision:0006`](../../../../../docs/decisions/0006-credit-system.md)
 * eliminó las retenciones y con ellas todo lo que este contexto tenía que ver
 * con la economía.
 *
 * `grantedVia` dice por qué camino se llegó. Es lo que permite responder
 * meses después por qué esta persona tiene acceso, que es exactamente la
 * pregunta que se hace cuando algo va mal.
 */
final readonly class BetaReaderAccessGranted implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private BetaReaderAccessId $accessId,
        private WorkId $workId,
        private AuthorId $authorId,
        private ReaderId $readerId,
        private string $grantedVia,
        private \DateTimeImmutable $grantedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'BetaReaderAccessGranted';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->grantedAt;
    }

    public function payload(): array
    {
        return [
            'accessId' => $this->accessId->value(),
            'workId' => $this->workId->value(),
            'authorId' => $this->authorId->value(),
            'readerId' => $this->readerId->value(),
            'grantedVia' => $this->grantedVia,
            'grantedAt' => $this->grantedAt->format(\DATE_ATOM),
        ];
    }
}
