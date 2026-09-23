<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `ChapterCorrectabilityChanged`, as `Feedback` models it (`FEAT-CRD-009`).
 *
 * **A boolean, and this context wants nothing more.** `Credits` weighed a
 * balance against a price and counted how many corrections are under way;
 * what arrives here is the conclusion. Asking for the reasoning would drag
 * the economy into a context that has no business knowing it.
 */
final readonly class ChapterCorrectabilityChanged implements IncomingIntegrationEvent
{
    private function __construct(
        public string $chapterId,
        public string $workId,
        public bool $correctable,
        private string $eventId,
        private \DateTimeImmutable $changedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'ChapterCorrectabilityChanged';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $chapterId = $payload['chapterId'] ?? null;
        $workId = $payload['workId'] ?? null;
        $correctable = $payload['correctable'] ?? null;

        if (!\is_string($chapterId) || !\is_string($workId)) {
            throw new \InvalidArgumentException('ChapterCorrectabilityChanged carries no chapter or work.');
        }

        if (!\is_bool($correctable)) {
            throw new \InvalidArgumentException('ChapterCorrectabilityChanged carries no answer.');
        }

        return new self($chapterId, $workId, $correctable, $eventId, $occurredAt);
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
            'correctable' => $this->correctable,
            'changedAt' => $this->changedAt->format(\DATE_ATOM),
        ];
    }
}
