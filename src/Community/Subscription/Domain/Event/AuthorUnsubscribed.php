<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Subscription\Domain\Event;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * Alguien ha dejado de seguir a un autor (`FEAT-COM-010` `RN-6`).
 *
 * **Existe porque la otra mitad no basta.** Quien proyecta el grafo necesita
 * los dos hechos: un seguimiento que se deshace sin avisar deja a todo el
 * mundo con una copia que envejece mal, y en este caso envejece hacia el lado
 * peligroso — alguien seguiría contando como seguidor, y con él dentro de una
 * audiencia `FOLLOWERS`, después de haberse ido.
 *
 * Nadie lo notifica: dejar de seguir es asunto de quien lo hace, y avisar al
 * autor convertiría una acción discreta en un desaire con acuse de recibo.
 */
final readonly class AuthorUnsubscribed implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private MemberId $subscriberId,
        private MemberId $authorId,
        private \DateTimeImmutable $unsubscribedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'AuthorUnsubscribed';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->unsubscribedAt;
    }

    public function payload(): array
    {
        return [
            'subscriberId' => $this->subscriberId->value(),
            'authorId' => $this->authorId->value(),
            'unsubscribedAt' => $this->unsubscribedAt->format(\DATE_ATOM),
        ];
    }
}
