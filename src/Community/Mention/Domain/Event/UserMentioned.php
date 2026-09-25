<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Mention\Domain\Event;

use LectoresBeta\Community\Mention\Domain\Enum\MentionSubject;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Post\Domain\ValueObject\PostId;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * Han nombrado a alguien en una publicación o en un comentario
 * (`FEAT-COM-032`).
 *
 * **No se publica cuando el mencionado no puede ver dónde se le menciona**
 * (`RN-4`). La comprobación se hace aquí, en el contexto que conoce la
 * audiencia, y no se delega en quien avisa: un aviso sobre algo que no se
 * puede abrir contaría que existe, y una mención se convertiría en la vía
 * para filtrar lo que se escribió para otros.
 *
 * Tampoco cuando alguien se menciona a sí mismo (`RN-5`).
 *
 * No lleva el nombre del mencionado ni el texto. El nombre se resuelve al
 * mostrar —de eso trata toda esta funcionalidad— y el texto no hace falta
 * para avisar.
 */
final readonly class UserMentioned implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private MemberId $mentionedUserId,
        private MemberId $byUserId,
        private PostId $postId,
        private MentionSubject $subjectKind,
        private string $subjectId,
        private \DateTimeImmutable $mentionedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'UserMentioned';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->mentionedAt;
    }

    public function payload(): array
    {
        return [
            'mentionedUserId' => $this->mentionedUserId->value(),
            'byUserId' => $this->byUserId->value(),
            'postId' => $this->postId->value(),
            'subjectKind' => $this->subjectKind->value,
            'subjectId' => $this->subjectId,
            'mentionedAt' => $this->mentionedAt->format(\DATE_ATOM),
        ];
    }
}
