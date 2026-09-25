<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Domain\Event;

use LectoresBeta\Community\Interaction\Domain\ValueObject\ChapterCommentId;
use LectoresBeta\Community\Interaction\Domain\ValueObject\ChapterId;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * Alguien ha comentado bajo el texto de un capítulo (`FEAT-COM-036`).
 *
 * **No es una corrección, y por eso no lo escucha `Credits`.** Que el
 * contexto de la economía no esté entre los consumidores es la forma más
 * clara de decir que comentar no mueve un crédito: lo que se paga es el
 * cuestionario respondido (`FEAT-FBK-003`), y confundir las dos cosas ya pasó
 * una vez en la documentación de este producto.
 *
 * Lleva **a quién avisar** y no el texto: un aviso no necesita el cuerpo de
 * lo escrito, y copiarlo aquí lo pondría en una cola que persiste, reintenta
 * y aparta mensajes.
 *
 * `parentAuthorId` viaja porque una respuesta avisa a dos personas distintas
 * —quien escribió el comentario y el autor de la obra— y sin él
 * `Notification` tendría que preguntarle a `Community` quién es cada cual,
 * que es justo la llamada que un hecho evita.
 */
final readonly class ChapterCommented implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private ChapterId $chapterId,
        private string $workId,
        private ChapterCommentId $commentId,
        private MemberId $workAuthorId,
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
        return 'ChapterCommented';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->commentedAt;
    }

    public function payload(): array
    {
        return [
            'chapterId' => $this->chapterId->value(),
            'workId' => $this->workId,
            'commentId' => $this->commentId->value(),
            'workAuthorId' => $this->workAuthorId->value(),
            'commentAuthorId' => $this->commentAuthorId->value(),
            'parentAuthorId' => $this->parentAuthorId?->value(),
            'commentedAt' => $this->commentedAt->format(\DATE_ATOM),
        ];
    }
}
