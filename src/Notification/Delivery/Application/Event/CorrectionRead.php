<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `CorrectionRead`, tal y como lo modela `Notification` (`FEAT-FBK-004`
 * `RN-7`).
 *
 * Sirve para **retirar** un aviso, que es de las pocas cosas que este
 * contexto hace sin crear nada. Un centro de notificaciones que sigue
 * marcando como nuevo algo que ya se ha leído deja de significar nada, y
 * entonces la gente deja de mirarlo.
 *
 * Quien abre la corrección es **el autor**, y el aviso que se retira es el
 * suyo. El campo se llamó `readerId` hasta `FEAT-NOT-006` llevando dentro el
 * identificador del autor; se sigue aceptando el nombre viejo para no perder
 * lo que estuviera en la cola durante el despliegue, y se puede quitar en
 * cuanto no quede nada de antes ahí dentro.
 */
final readonly class CorrectionRead implements IncomingIntegrationEvent
{
    private function __construct(
        public string $correctionId,
        public string $authorId,
        private string $eventId,
        private \DateTimeImmutable $readAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'CorrectionRead';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $correctionId = $payload['correctionId'] ?? null;
        $authorId = $payload['authorId'] ?? $payload['readerId'] ?? null;

        if (!\is_string($correctionId) || !\is_string($authorId)) {
            throw new \InvalidArgumentException('CorrectionRead carries no correction or author.');
        }

        return new self($correctionId, $authorId, $eventId, $occurredAt);
    }

    public function eventId(): string
    {
        return $this->eventId;
    }

    public function eventName(): string
    {
        return self::subscribesTo();
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
