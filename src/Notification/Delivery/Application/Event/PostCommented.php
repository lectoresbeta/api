<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `PostCommented`, tal y como lo modela `Notification` (`FEAT-COM-006`
 * `RN-8`, `FEAT-COM-031`).
 *
 * El gemelo de `ChapterCommented` sobre una publicación del muro, y con la
 * misma forma: trae **a quién avisar** y no el texto.
 *
 * `parentAuthorId` viene nulo cuando es un comentario de primer nivel; cuando
 * es una respuesta hay dos personas a las que avisar, y son distintas.
 */
final readonly class PostCommented implements IncomingIntegrationEvent
{
    private function __construct(
        public string $postId,
        public string $commentId,
        public string $postAuthorId,
        public string $commentAuthorId,
        public ?string $parentAuthorId,
        private string $eventId,
        private \DateTimeImmutable $commentedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'PostCommented';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $postId = $payload['postId'] ?? null;
        $commentId = $payload['commentId'] ?? null;
        $postAuthorId = $payload['postAuthorId'] ?? null;
        $commentAuthorId = $payload['commentAuthorId'] ?? null;
        $parentAuthorId = $payload['parentAuthorId'] ?? null;

        if (
            !\is_string($postId) || !\is_string($commentId)
            || !\is_string($postAuthorId) || !\is_string($commentAuthorId)
        ) {
            throw new \InvalidArgumentException('PostCommented carries no post, comment or people.');
        }

        return new self(
            $postId,
            $commentId,
            $postAuthorId,
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
            'postId' => $this->postId,
            'commentId' => $this->commentId,
            'postAuthorId' => $this->postAuthorId,
            'commentAuthorId' => $this->commentAuthorId,
            'parentAuthorId' => $this->parentAuthorId,
            'commentedAt' => $this->commentedAt->format(\DATE_ATOM),
        ];
    }
}
