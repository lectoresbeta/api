<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderAccess\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `WorkArchived`, tal y como lo modela `Reading` (`FEAT-WRK-006` `RN-6`).
 *
 * Aquí significa una cosa: **ya no hay nada que leer**, así que los accesos
 * de lector beta a esa obra dejan de tener objeto.
 *
 * Qué es un acceso y cuándo deja de valer lo decide este contexto, no `Work`.
 * Si `Work` revocase accesos tendría que aprender un modelo que no es suyo —
 * es la misma razón por la que `Community` no los toca al bloquear a alguien.
 */
final readonly class WorkArchived implements IncomingIntegrationEvent
{
    private function __construct(
        public string $workId,
        public string $authorId,
        private string $eventId,
        private \DateTimeImmutable $archivedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'WorkArchived';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $workId = $payload['workId'] ?? null;
        $authorId = $payload['authorId'] ?? null;

        if (!\is_string($workId) || !\is_string($authorId)) {
            throw new \InvalidArgumentException('WorkArchived carries no work or author.');
        }

        return new self($workId, $authorId, $eventId, $occurredAt);
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
        return $this->archivedAt;
    }

    public function payload(): array
    {
        return [
            'workId' => $this->workId,
            'authorId' => $this->authorId,
            'archivedAt' => $this->archivedAt->format(\DATE_ATOM),
        ];
    }
}
