<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Domain\Event;

use LectoresBeta\Community\Interaction\Domain\ValueObject\PostCommentId;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Post\Domain\ValueObject\PostId;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * Alguien ha comentado una publicación, o ha respondido a un comentario
 * (`FEAT-COM-006` `RN-8`, `FEAT-COM-031`).
 *
 * Lleva **a quién hay que avisar** y no el texto. Un aviso no necesita el
 * cuerpo de lo escrito, y copiarlo aquí lo pondría en una cola que persiste,
 * reintenta y aparta mensajes.
 *
 * `parentAuthorId` viaja porque una respuesta avisa a dos personas distintas
 * —quien escribió el comentario y quien escribió la publicación— y sin él
 * `Notification` tendría que preguntarle a `Community` quién es cada cual,
 * que es exactamente la llamada que un hecho evita.
 */
final readonly class PostCommented implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private PostId $postId,
        private PostCommentId $commentId,
        private MemberId $postAuthorId,
        private MemberId $commentAuthorId,
        private ?MemberId $parentAuthorId,
        private \DateTimeImmutable $commentedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'PostCommented';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->commentedAt;
    }

    public function payload(): array
    {
        return [
            'postId' => $this->postId->value(),
            'commentId' => $this->commentId->value(),
            'postAuthorId' => $this->postAuthorId->value(),
            'commentAuthorId' => $this->commentAuthorId->value(),
            'parentAuthorId' => $this->parentAuthorId?->value(),
            'commentedAt' => $this->commentedAt->format(\DATE_ATOM),
        ];
    }
}
