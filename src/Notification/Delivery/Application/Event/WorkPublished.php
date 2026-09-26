<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `WorkPublished`, tal y como lo modela `Notification` (`FEAT-NOT-004`).
 *
 * De todo lo que trae el hecho, aquí solo hacen falta **tres cosas**: quién
 * publicó, qué, y cómo se llama. El recuento de palabras, los capítulos y las
 * temáticas son para quien recomienda, no para quien avisa.
 *
 * El título viaja **dentro del hecho** y no se pregunta después, que es lo
 * correcto para un aviso: describe algo que pasó, y si la obra se retitula
 * mañana el aviso de ayer conserva el nombre de ayer.
 */
final readonly class WorkPublished implements IncomingIntegrationEvent
{
    private function __construct(
        public string $workId,
        public string $authorId,
        public string $title,
        private string $eventId,
        private \DateTimeImmutable $publishedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'WorkPublished';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $workId = $payload['workId'] ?? null;
        $authorId = $payload['authorId'] ?? null;
        $title = $payload['title'] ?? null;

        if (!\is_string($workId) || !\is_string($authorId) || !\is_string($title)) {
            throw new \InvalidArgumentException('WorkPublished carries no work, author or title.');
        }

        return new self($workId, $authorId, $title, $eventId, $occurredAt);
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
        return $this->publishedAt;
    }

    public function payload(): array
    {
        return [
            'workId' => $this->workId,
            'authorId' => $this->authorId,
            'title' => $this->title,
            'publishedAt' => $this->publishedAt->format(\DATE_ATOM),
        ];
    }
}
