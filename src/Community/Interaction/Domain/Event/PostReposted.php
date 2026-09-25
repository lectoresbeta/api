<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Domain\Event;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Post\Domain\ValueObject\PostId;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * Alguien ha vuelto a sacar la publicación de otro (`FEAT-COM-019`).
 *
 * Lo consume `Notification` para avisar al autor original, que es a quien le
 * interesa. Quien repostea ya sabe lo que ha hecho.
 */
final readonly class PostReposted implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private PostId $postId,
        private MemberId $originalAuthorId,
        private MemberId $repostedBy,
        private \DateTimeImmutable $repostedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'PostReposted';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->repostedAt;
    }

    public function payload(): array
    {
        return [
            'postId' => $this->postId->value(),
            'originalAuthorId' => $this->originalAuthorId->value(),
            'repostedBy' => $this->repostedBy->value(),
            'repostedAt' => $this->repostedAt->format(\DATE_ATOM),
        ];
    }
}
