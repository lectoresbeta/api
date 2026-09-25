<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Domain\Event;

use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * El autor ha dado créditos propios a una corrección que le sirvió
 * (`FEAT-CRD-017`).
 *
 * **`Credits` cuenta lo que ha pasado; nadie se lo ha pedido.** Es la misma
 * dirección que `CreditsAdded` y `CreditsSpent`: este contexto no publica
 * contratos y no responde preguntas, pero sí narra sus hechos para que otros
 * proyecten lo que necesiten.
 *
 * Lo consumen `Feedback`, para que autor y corrector vean la propina en la
 * corrección, y `Community`, para la reputación del corrector — que es lo que
 * permite que la propina alimente un ranking **sin que `Credits` sepa nada de
 * rankings**.
 *
 * Que la propina sea la señal de calidad más fiable del sistema tiene una
 * razón simple: es la única que alguien ha **pagado de su bolsillo**, así que
 * es mucho más difícil de falsear que un «me gusta».
 */
final readonly class CorrectionTipped implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private string $correctionId,
        private UserId $authorId,
        private UserId $readerId,
        private int $amount,
        private \DateTimeImmutable $tippedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'CorrectionTipped';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->tippedAt;
    }

    public function payload(): array
    {
        return [
            'correctionId' => $this->correctionId,
            'authorId' => $this->authorId->value(),
            'readerId' => $this->readerId->value(),
            'amount' => $this->amount,
            'tippedAt' => $this->tippedAt->format(\DATE_ATOM),
        ];
    }
}
