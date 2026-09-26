<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Catalogue\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `FeedbackSubmitted`, tal y como lo modela `Work` (`FEAT-WRK-012`).
 *
 * Dos campos de siete, y ninguno es el contenido: al catálogo le basta con
 * **qué obra** recibió una corrección y **cuál**, para no contarla dos veces.
 *
 * Cuenta hacia abajo: cada corrección recibida hace descender la obra
 * ([`decision:0008`](../../../../../docs/decisions/0008-catalogue-ordering.md)).
 * Es lo contrario de un contador de popularidad, y es a propósito.
 */
final readonly class FeedbackSubmitted implements IncomingIntegrationEvent
{
    private function __construct(
        public string $correctionId,
        public string $workId,
        private string $eventId,
        private \DateTimeImmutable $submittedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'FeedbackSubmitted';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $correctionId = $payload['correctionId'] ?? null;
        $workId = $payload['workId'] ?? null;

        if (!\is_string($correctionId) || !\is_string($workId)) {
            throw new \InvalidArgumentException('FeedbackSubmitted carries no correction or work.');
        }

        return new self($correctionId, $workId, $eventId, $occurredAt);
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
        return $this->submittedAt;
    }

    public function payload(): array
    {
        return [
            'correctionId' => $this->correctionId,
            'workId' => $this->workId,
            'submittedAt' => $this->submittedAt->format(\DATE_ATOM),
        ];
    }
}
