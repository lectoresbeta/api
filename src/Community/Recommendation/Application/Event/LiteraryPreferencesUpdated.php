<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Recommendation\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `LiteraryPreferencesUpdated`, tal y como lo modela `Community`.
 *
 * Una clase distinta de la que publica `User`, a propósito
 * ([`decision:0013`](../../../../../docs/decisions/0013-integration-events-travel-without-class-names.md)):
 * aquí no se importa nada de `User` y allí nadie sabe que esto existe. Lo que
 * comparten es el nombre del hecho y la forma de su payload.
 *
 * `Community` lo consume para una sola cosa: poder sugerir autores por
 * afinidad **sin consultar la tabla de géneros de `User`** (`FEAT-COM-016`
 * `RN-1`).
 */
final readonly class LiteraryPreferencesUpdated implements IncomingIntegrationEvent
{
    /**
     * @param list<string> $genres
     */
    private function __construct(
        public string $userId,
        public array $genres,
        private string $eventId,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'LiteraryPreferencesUpdated';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $userId = $payload['userId'] ?? null;
        $genres = $payload['genres'] ?? [];

        if (!\is_string($userId) || '' === $userId) {
            throw new \InvalidArgumentException('LiteraryPreferencesUpdated needs a user.');
        }

        if (!\is_array($genres)) {
            throw new \InvalidArgumentException('LiteraryPreferencesUpdated needs a list of genres.');
        }

        return new self($userId, array_values(array_filter($genres, \is_string(...))), $eventId, $occurredAt);
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
        return $this->updatedAt;
    }

    public function payload(): array
    {
        return ['userId' => $this->userId, 'genres' => $this->genres];
    }
}
