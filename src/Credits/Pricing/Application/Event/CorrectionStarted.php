<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `CorrectionStarted`, as `Credits` models it (`FEAT-CRD-009`).
 *
 * Somebody opened the correction panel on a chapter. `Feedback` did not ask
 * permission and did not wait for an answer — it decided against its own
 * projection — so this is a fact, not a request.
 *
 * What `Credits` does with it is quote the price, which is the number that
 * will be charged and earned however long the reader takes and whatever the
 * author edits meanwhile (`RN-2`).
 */
final readonly class CorrectionStarted implements IncomingIntegrationEvent
{
    private function __construct(
        public string $chapterId,
        public string $workId,
        public string $authorId,
        public string $readerId,
        private string $eventId,
        private \DateTimeImmutable $startedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'CorrectionStarted';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $chapterId = $payload['chapterId'] ?? null;
        $workId = $payload['workId'] ?? null;
        $authorId = $payload['authorId'] ?? null;
        $readerId = $payload['readerId'] ?? null;

        if (!\is_string($chapterId) || !\is_string($workId) || !\is_string($authorId) || !\is_string($readerId)) {
            throw new \InvalidArgumentException('CorrectionStarted is missing one of its four identifiers.');
        }

        return new self($chapterId, $workId, $authorId, $readerId, $eventId, $occurredAt);
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
        return $this->startedAt;
    }

    public function payload(): array
    {
        return [
            'chapterId' => $this->chapterId,
            'workId' => $this->workId,
            'authorId' => $this->authorId,
            'readerId' => $this->readerId,
            'startedAt' => $this->startedAt->format(\DATE_ATOM),
        ];
    }
}
