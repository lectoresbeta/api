<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderAccess\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `CorrectionDraftDiscarded`, tal y como lo modela `Reading`
 * (`FEAT-RDG-001` `RN-5`).
 *
 * Quien pulsa «descartar» está diciendo que no va a hacerlo, y eso es lo que
 * deshace el acceso que empezar le había concedido. Necesita `workId` porque
 * el acceso es a la obra, no al capítulo.
 */
final readonly class CorrectionDraftDiscarded implements IncomingIntegrationEvent
{
    private function __construct(
        public string $workId,
        public string $readerId,
        private string $eventId,
        private \DateTimeImmutable $discardedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'CorrectionDraftDiscarded';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $workId = $payload['workId'] ?? null;
        $readerId = $payload['readerId'] ?? null;

        if (!\is_string($workId) || !\is_string($readerId)) {
            throw new \InvalidArgumentException('CorrectionDraftDiscarded carries no work or reader.');
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
        return $this->discardedAt;
    }

    public function payload(): array
    {
        return [
            'workId' => $this->workId,
            'readerId' => $this->readerId,
            'discardedAt' => $this->discardedAt->format(\DATE_ATOM),
        ];
    }
}
