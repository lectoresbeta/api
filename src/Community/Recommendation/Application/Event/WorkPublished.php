<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Recommendation\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `WorkPublished`, tal y como lo modela `Community`: **quién ha publicado y
 * de qué va** (`FEAT-COM-016` `RN-1`).
 *
 * De todo lo que trae el hecho, aquí solo interesan el autor y las temáticas.
 * Con ellos se mantiene la proyección de en qué géneros escribe cada cual,
 * que es lo que permite responder «autores que te pueden gustar» sin leer una
 * sola tabla de `Work`.
 */
final readonly class WorkPublished implements IncomingIntegrationEvent
{
    /**
     * @param list<string> $genres
     */
    private function __construct(
        public string $authorId,
        public array $genres,
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
        $authorId = $payload['authorId'] ?? null;
        $genres = $payload['genres'] ?? [];

        if (!\is_string($authorId) || '' === $authorId) {
            throw new \InvalidArgumentException('WorkPublished needs an author.');
        }

        if (!\is_array($genres)) {
            throw new \InvalidArgumentException('WorkPublished needs a list of genres.');
        }

        return new self($authorId, array_values(array_filter($genres, \is_string(...))), $eventId, $occurredAt);
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
        return ['authorId' => $this->authorId, 'genres' => $this->genres];
    }
}
