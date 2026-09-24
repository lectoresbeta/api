<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Subscription\Domain\Event;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * Alguien ha empezado a seguir a un autor (`FEAT-COM-010` `RN-6`).
 *
 * **Los dos identificadores y nada más.** Ni nombres ni perfiles: quien lo
 * consume tiene su propia copia de las personas, y copiar aquí un nombre solo
 * añadiría un sitio donde envejece.
 *
 * Lo consume `User` para proyectar el grafo que necesita para resolver las
 * audiencias `FOLLOWERS`, y lo consumirá `Notification` para avisar al autor.
 */
final readonly class AuthorSubscribed implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private MemberId $subscriberId,
        private MemberId $authorId,
        private \DateTimeImmutable $subscribedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'AuthorSubscribed';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->subscribedAt;
    }

    public function payload(): array
    {
        return [
            'subscriberId' => $this->subscriberId->value(),
            'authorId' => $this->authorId->value(),
            'subscribedAt' => $this->subscribedAt->format(\DATE_ATOM),
        ];
    }
}
