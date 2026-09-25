<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `CorrectionClosed`, tal y como lo modela `Notification`.
 *
 * Alguien tenía una corrección a medias sobre algo que ha dejado de estar
 * disponible. **El aviso es para quien la escribía**, y conviene que el
 * mensaje no le haga pensar que ha hecho algo mal: no lo ha hecho.
 *
 * `reason` distingue las tres causas —la obra se bloqueó, su autor la retiró,
 * o le ocultó el capítulo— porque no significan lo mismo para quien lo lee.
 */
final readonly class CorrectionClosed implements IncomingIntegrationEvent
{
    private function __construct(
        public string $correctionId,
        public string $chapterId,
        public string $workId,
        public string $readerId,
        public string $reason,
        private string $eventId,
        private \DateTimeImmutable $closedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'CorrectionClosed';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $correctionId = $payload['correctionId'] ?? null;
        $chapterId = $payload['chapterId'] ?? null;
        $workId = $payload['workId'] ?? null;
        $readerId = $payload['readerId'] ?? null;
        $reason = $payload['reason'] ?? null;

        if (!\is_string($correctionId) || !\is_string($chapterId) || !\is_string($workId)
            || !\is_string($readerId) || !\is_string($reason)) {
            throw new \InvalidArgumentException('CorrectionClosed carries no correction, chapter, work, reader or reason.');
        }

        return new self($correctionId, $chapterId, $workId, $readerId, $reason, $eventId, $occurredAt);
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
        return $this->closedAt;
    }

    public function payload(): array
    {
        return [
            'correctionId' => $this->correctionId,
            'chapterId' => $this->chapterId,
            'workId' => $this->workId,
            'readerId' => $this->readerId,
            'reason' => $this->reason,
            'closedAt' => $this->closedAt->format(\DATE_ATOM),
        ];
    }
}
