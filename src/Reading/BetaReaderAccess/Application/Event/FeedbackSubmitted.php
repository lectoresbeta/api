<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderAccess\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `FeedbackSubmitted`, tal y como lo modela `Reading` (`FEAT-RDG-001`).
 *
 * De todo lo que este hecho significa —y significa mucho: es lo único que
 * mueve créditos— a este contexto le importa una sola cosa: **quien entrega
 * una corrección se ha ganado el acceso**, y a partir de ahí abandonar otro
 * borrador ya no se lo quita.
 *
 * Dos campos de siete. Es exactamente lo que `decision:0013` permite: un
 * consumidor declara lo que necesita, no lo que el publicador manda.
 */
final readonly class FeedbackSubmitted implements IncomingIntegrationEvent
{
    private function __construct(
        public string $workId,
        public string $readerId,
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
        $workId = $payload['workId'] ?? null;
        $readerId = $payload['readerId'] ?? null;

        if (!\is_string($workId) || !\is_string($readerId)) {
            throw new \InvalidArgumentException('FeedbackSubmitted carries no work or reader.');
        }

        return new self($workId, $readerId, $eventId, $occurredAt);
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
            'workId' => $this->workId,
            'readerId' => $this->readerId,
            'submittedAt' => $this->submittedAt->format(\DATE_ATOM),
        ];
    }
}
