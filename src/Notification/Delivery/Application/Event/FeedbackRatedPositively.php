<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `FeedbackRatedPositively`, tal y como lo modela `Notification`.
 *
 * El aviso es **para quien escribió la corrección**, que es lo único que la
 * plataforma le devuelve a cambio de su trabajo.
 *
 * Nunca lleva texto: un aviso dice que hay algo que leer, y se lee dentro con
 * las comprobaciones de quien lo posee.
 */
final readonly class FeedbackRatedPositively implements IncomingIntegrationEvent
{
    private function __construct(
        public string $correctionId,
        public string $workId,
        public string $chapterId,
        public string $readerId,
        private string $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'FeedbackRatedPositively';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $correctionId = $payload['correctionId'] ?? null;
        $workId = $payload['workId'] ?? null;
        $chapterId = $payload['chapterId'] ?? null;
        $readerId = $payload['readerId'] ?? null;

        if (!\is_string($correctionId) || !\is_string($workId) || !\is_string($chapterId) || !\is_string($readerId)) {
            throw new \InvalidArgumentException('FeedbackRatedPositively carries no correction, work, chapter or reader.');
        }

        return new self($correctionId, $workId, $chapterId, $readerId, $eventId, $occurredAt);
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
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'correctionId' => $this->correctionId,
            'workId' => $this->workId,
            'chapterId' => $this->chapterId,
            'readerId' => $this->readerId,
        ];
    }
}
