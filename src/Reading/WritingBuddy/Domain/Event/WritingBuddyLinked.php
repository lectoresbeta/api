<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\WritingBuddy\Domain\Event;

use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Reading\WritingBuddy\Domain\ValueObject\WritingBuddyLinkId;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * El vínculo queda aceptado (`FEAT-RDG-009`).
 *
 * Lleva **los dos identificadores y en el orden de la propuesta**, porque el
 * vínculo es recíproco pero el hecho no lo es: quien lo propuso está
 * esperando respuesta y quien la dio ya sabe lo que ha hecho.
 *
 * **Rechazar no publica nada.** Es la misma decisión que con
 * `AuthorUnsubscribed`: avisar a alguien de que le han dicho que no
 * convertiría una respuesta discreta en un desaire con acuse de recibo. Quien
 * propuso lo ve en su lista, que es donde lo fue a mirar.
 */
final readonly class WritingBuddyLinked implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private WritingBuddyLinkId $linkId,
        private ReaderId $proposerId,
        private ReaderId $partnerId,
        private \DateTimeImmutable $linkedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'WritingBuddyLinked';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->linkedAt;
    }

    public function payload(): array
    {
        return [
            'linkId' => $this->linkId->value(),
            'proposerId' => $this->proposerId->value(),
            'partnerId' => $this->partnerId->value(),
            'linkedAt' => $this->linkedAt->format(\DATE_ATOM),
        ];
    }
}
