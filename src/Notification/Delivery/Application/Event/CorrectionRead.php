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
 */
final readonly class CorrectionRead implements IncomingIntegrationEvent
{
    private function __construct(
        public string $correctionId,
        public string $readerId,
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
        $readerId = $payload['readerId'] ?? null;

        if (!\is_string($correctionId) || !\is_string($readerId)) {
            throw new \InvalidArgumentException('CorrectionRead carries no correction or reader.');
        }

        return new self($correctionId, $readerId, $eventId, $occurredAt);
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
            'readerId' => $this->readerId,
            'readAt' => $this->readAt->format(\DATE_ATOM),
        ];
    }
}
