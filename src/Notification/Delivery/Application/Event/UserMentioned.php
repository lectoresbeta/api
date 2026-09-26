<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `UserMentioned`, tal y como lo modela `Notification` (`FEAT-COM-032`).
 *
 * **La comprobación de que el mencionado puede ver dónde se le menciona ya
 * está hecha** (`RN-4`), y la hizo `Community`, que es quien conoce la
 * audiencia. Aquí no se repite: este hecho solo se publica cuando el aviso es
 * legítimo, y volver a preguntarlo desde aquí sería una llamada síncrona al
 * contexto que el evento existe para no molestar.
 *
 * `subjectKind` distingue si la mención estaba en la publicación o en un
 * comentario, que es lo que el cliente necesita para saber a dónde llevar.
 */
final readonly class UserMentioned implements IncomingIntegrationEvent
{
    private function __construct(
        public string $mentionedUserId,
        public string $byUserId,
        public string $postId,
        public string $subjectKind,
        public string $subjectId,
        private string $eventId,
        private \DateTimeImmutable $mentionedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'UserMentioned';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $mentionedUserId = $payload['mentionedUserId'] ?? null;
        $byUserId = $payload['byUserId'] ?? null;
        $postId = $payload['postId'] ?? null;
        $subjectKind = $payload['subjectKind'] ?? null;
        $subjectId = $payload['subjectId'] ?? null;

        if (
            !\is_string($mentionedUserId) || !\is_string($byUserId) || !\is_string($postId)
            || !\is_string($subjectKind) || !\is_string($subjectId)
        ) {
            throw new \InvalidArgumentException('UserMentioned carries no people, post or subject.');
        }

        return new self($mentionedUserId, $byUserId, $postId, $subjectKind, $subjectId, $eventId, $occurredAt);
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
        return $this->mentionedAt;
    }

    public function payload(): array
    {
        return [
            'mentionedUserId' => $this->mentionedUserId,
            'byUserId' => $this->byUserId,
            'postId' => $this->postId,
            'subjectKind' => $this->subjectKind,
            'subjectId' => $this->subjectId,
            'mentionedAt' => $this->mentionedAt->format(\DATE_ATOM),
        ];
    }
}
