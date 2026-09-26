<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Domain\Event;

use LectoresBeta\Community\Post\Domain\Enum\PostAudience;
use LectoresBeta\Community\Post\Domain\Enum\PostFormat;
use LectoresBeta\Community\Post\Domain\Enum\PostType;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Post\Domain\ValueObject\PostId;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * Alguien ha publicado en el muro (`FEAT-COM-002` `RN-10`).
 *
 * **Lleva la audiencia, y esa es la parte que importa.** Lo consume
 * `Notification` para avisar a quienes siguen al autor, y avisar de algo que
 * no se puede abrir es, además de inútil, una filtración: el aviso contaría
 * que existe una publicación que su autor escribió para otros.
 *
 * No lleva el texto. Un aviso no necesita el cuerpo de lo publicado, y
 * copiarlo aquí lo pondría en una cola que persiste, reintenta y aparta
 * mensajes.
 */
final readonly class PostPublished implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private PostId $postId,
        private MemberId $authorId,
        private PostType $type,
        private PostFormat $format,
        private PostAudience $audience,
        private \DateTimeImmutable $publishedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'PostPublished';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function payload(): array
    {
        return [
            'postId' => $this->postId->value(),
            'authorId' => $this->authorId->value(),
            'type' => $this->type->value,
            'format' => $this->format->value,
            'audience' => $this->audience->value,
            'publishedAt' => $this->publishedAt->format(\DATE_ATOM),
        ];
    }
}
