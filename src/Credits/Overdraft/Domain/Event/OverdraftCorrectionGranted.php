<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Overdraft\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * Alguien ha corregido un capítulo que su autor no podía pagar
 * (`FEAT-CRD-019`).
 *
 * Es el hecho **económico**, y lo publica `Credits` porque es suyo. Lo que se
 * hace con él es de cada consumidor: `Notification` manda el correo gancho.
 * **Quién esconde el contenido es `Feedback`**, y ya lo hace desde
 * `CreditBalanceWentNegative` (`FEAT-CRD-018`): dos hechos distintos que
 * bloquearan la misma corrección serían dos verdades sobre lo mismo.
 *
 * Lleva `creditsNeeded` porque de eso vive el gancho: «te faltan 7 créditos»
 * motiva mucho más que «repón saldo» (`RN-4`, `C-31`). Y lleva metadatos del
 * trabajo —capítulo, palabras— que **no son el contenido**: el autor ve qué
 * le espera, no lo que dice.
 */
final readonly class OverdraftCorrectionGranted implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private string $authorId,
        private string $readerId,
        private string $correctionId,
        private string $chapterId,
        private string $workId,
        private int $amount,
        private int $creditsNeeded,
        private \DateTimeImmutable $grantedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'OverdraftCorrectionGranted';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->grantedAt;
    }

    public function payload(): array
    {
        return [
            'authorId' => $this->authorId,
            'readerId' => $this->readerId,
            'correctionId' => $this->correctionId,
            'chapterId' => $this->chapterId,
            'workId' => $this->workId,
            'amount' => $this->amount,
            'creditsNeeded' => $this->creditsNeeded,
            'grantedAt' => $this->grantedAt->format(\DATE_ATOM),
        ];
    }
}
