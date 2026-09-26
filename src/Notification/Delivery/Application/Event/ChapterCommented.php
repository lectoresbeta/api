<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `ChapterCommented`, tal y como lo modela `Notification` (`FEAT-COM-036`).
 *
 * Trae **a quién avisar** y no el texto. `parentAuthorId` viene nulo cuando
 * es un comentario de primer nivel; cuando es una respuesta, hay dos personas
 * a las que avisar y son distintas.
 */
final readonly class ChapterCommented implements IncomingIntegrationEvent
{
    private function __construct(
        public string $chapterId,
        public string $workId,
        public string $commentId,
        public string $workAuthorId,
        public string $commentAuthorId,
        public ?string $parentAuthorId,
        private string $eventId,
        private \DateTimeImmutable $commentedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'ChapterCommented';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $chapterId = $payload['chapterId'] ?? null;
        $workId = $payload['workId'] ?? null;
        $commentId = $payload['commentId'] ?? null;
        $workAuthorId = $payload['workAuthorId'] ?? null;
        $commentAuthorId = $payload['commentAuthorId'] ?? null;
        $parentAuthorId = $payload['parentAuthorId'] ?? null;

        if (
            !\is_string($chapterId) || !\is_string($workId) || !\is_string($commentId)
            || !\is_string($workAuthorId) || !\is_string($commentAuthorId)
        ) {
            throw new \InvalidArgumentException('ChapterCommented carries no chapter, work, comment or people.');
        }

        return new self(
            $chapterId,
            $workId,
            $commentId,
            $workAuthorId,
            $commentAuthorId,
            \is_string($parentAuthorId) ? $parentAuthorId : null,
            $eventId,
            $occurredAt,
        );
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
        return $this->commentedAt;
    }

    public function payload(): array
    {
        return [
            'chapterId' => $this->chapterId,
            'workId' => $this->workId,
            'commentId' => $this->commentId,
            'workAuthorId' => $this->workAuthorId,
            'commentAuthorId' => $this->commentAuthorId,
            'parentAuthorId' => $this->parentAuthorId,
            'commentedAt' => $this->commentedAt->format(\DATE_ATOM),
        ];
    }
}
