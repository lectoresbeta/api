<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * El autor ha abierto una corrección que había recibido (`FEAT-FBK-004`
 * `RN-7`).
 *
 * Lo escucha `Notification`, que retira el aviso pendiente: un centro de
 * notificaciones que sigue marcando como nuevo algo que ya se ha leído deja
 * de significar nada en una semana.
 *
 * **No lleva a quién avisar, porque no hay a quién avisar.** Quien escribió
 * la corrección no se entera de que se ha leído: sería una confirmación de
 * lectura entre dos personas que no han elegido tener una conversación
 * (`F-14`).
 *
 * Lo que sí lleva es **quién la ha abierto, que es el autor**. El campo se
 * llamó `readerId` en su día y llevaba dentro el identificador del autor:
 * quien lo consumía acertaba —el aviso que hay que retirar es el suyo— pero
 * por un nombre que decía lo contrario. Corregido en `FEAT-NOT-006`, antes de
 * que un segundo consumidor se lo creyera.
 */
final readonly class CorrectionRead implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private string $correctionId,
        private string $authorId,
        private \DateTimeImmutable $readAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'CorrectionRead';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->readAt;
    }

    public function payload(): array
    {
        return [
            'correctionId' => $this->correctionId,
            'authorId' => $this->authorId,
            'readAt' => $this->readAt->format(\DATE_ATOM),
        ];
    }
}
