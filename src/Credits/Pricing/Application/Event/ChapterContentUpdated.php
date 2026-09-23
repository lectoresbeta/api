<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `ChapterContentUpdated`, as `Credits` models it (`FEAT-CRD-016`).
 *
 * A **different class** from the one `Work` publishes
 * ([`decision:0013`](../../../../../docs/decisions/0013-integration-events-travel-without-class-names.md)):
 * nothing here imports anything from `Work`, and the two sides share only the
 * name of the fact and the shape of its payload.
 *
 * Note what it does **not** carry: a price, and not a word of the chapter.
 * `Work` publishes that a chapter is this long; deciding what that length
 * costs belongs here (`RN-1`).
 */
final readonly class ChapterContentUpdated implements IncomingIntegrationEvent
{
    private function __construct(
        public string $chapterId,
        public string $workId,
        public string $authorId,
        public int $position,
        public int $wordCount,
        private string $eventId,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'ChapterContentUpdated';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $chapterId = $payload['chapterId'] ?? null;
        $workId = $payload['workId'] ?? null;
        $authorId = $payload['authorId'] ?? null;
        $position = $payload['position'] ?? null;
        $wordCount = $payload['wordCount'] ?? null;

        if (!\is_string($chapterId) || !\is_string($workId) || !\is_string($authorId)) {
            throw new \InvalidArgumentException('ChapterContentUpdated carries no chapter, work or author.');
        }

        // A word count that arrived as something other than a number would
        // silently price every correction of this chapter at the floor.
        if (!\is_int($position) || !\is_int($wordCount) || $position < 1 || $wordCount < 0) {
            throw new \InvalidArgumentException('ChapterContentUpdated carries no usable position or word count.');
        }

        return new self($chapterId, $workId, $authorId, $position, $wordCount, $eventId, $occurredAt);
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
        return $this->updatedAt;
    }

    public function payload(): array
    {
        return [
            'chapterId' => $this->chapterId,
            'workId' => $this->workId,
            'authorId' => $this->authorId,
            'position' => $this->position,
            'wordCount' => $this->wordCount,
            'updatedAt' => $this->updatedAt->format(\DATE_ATOM),
        ];
    }
}
