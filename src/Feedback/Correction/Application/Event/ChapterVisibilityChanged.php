<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `ChapterVisibilityChanged`, tal y como lo modela `Feedback` (`FEAT-WRK-008`
 * `RN-5`).
 *
 * Solo interesa cuando el capítulo se **oculta**: volver a mostrarlo no deja
 * a nadie con trabajo a medias.
 */
final readonly class ChapterVisibilityChanged implements IncomingIntegrationEvent
{
    public const HIDDEN = 'HIDDEN';

    private function __construct(
        public string $chapterId,
        public string $workId,
        public string $visibility,
        private string $eventId,
        private \DateTimeImmutable $changedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'ChapterVisibilityChanged';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $chapterId = $payload['chapterId'] ?? null;
        $workId = $payload['workId'] ?? null;
        $visibility = $payload['visibility'] ?? null;

        if (!\is_string($chapterId) || !\is_string($workId) || !\is_string($visibility)) {
            throw new \InvalidArgumentException('ChapterVisibilityChanged carries no chapter, work or visibility.');
        }

        return new self($chapterId, $workId, $visibility, $eventId, $occurredAt);
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
        return $this->changedAt;
    }

    public function payload(): array
    {
        return [
            'chapterId' => $this->chapterId,
            'workId' => $this->workId,
            'visibility' => $this->visibility,
            'changedAt' => $this->changedAt->format(\DATE_ATOM),
        ];
    }
}
