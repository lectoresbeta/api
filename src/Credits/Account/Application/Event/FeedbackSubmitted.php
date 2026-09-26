<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `FeedbackSubmitted`, as `Credits` models it (`FEAT-CRD-006`).
 *
 * **The most important fact in the system**: it is the only moment credits
 * move. And note what it does not carry — no amount, and not a word of the
 * correction. `Feedback` says that a reader delivered work on a chapter; how
 * much that is worth was decided here when the correction started, and the
 * text is private material between two people, which a queue that retries
 * and parks messages is no place for.
 *
 * `chapterId` is not optional: the correction is per chapter, and a fact
 * without it would leave this context with nothing to price.
 */
final readonly class FeedbackSubmitted implements IncomingIntegrationEvent
{
    private function __construct(
        public string $correctionId,
        public string $chapterId,
        public string $workId,
        public string $authorId,
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
        $correctionId = $payload['correctionId'] ?? null;
        $chapterId = $payload['chapterId'] ?? null;
        $workId = $payload['workId'] ?? null;
        $authorId = $payload['authorId'] ?? null;
        $readerId = $payload['readerId'] ?? null;

        if (!\is_string($correctionId) || !\is_string($chapterId) || !\is_string($workId)) {
            throw new \InvalidArgumentException('FeedbackSubmitted is missing the correction it delivers.');
        }

        if (!\is_string($authorId) || !\is_string($readerId)) {
            throw new \InvalidArgumentException('FeedbackSubmitted is missing one of the two parties.');
        }

        return new self($correctionId, $chapterId, $workId, $authorId, $readerId, $eventId, $occurredAt);
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
            'chapterId' => $this->chapterId,
            'workId' => $this->workId,
            'authorId' => $this->authorId,
            'readerId' => $this->readerId,
            'submittedAt' => $this->submittedAt->format(\DATE_ATOM),
        ];
    }
}
