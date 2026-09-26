<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\ContentReview\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `ChapterContentUpdated`, tal y como lo modela `Moderation`: **hay un texto
 * nuevo que revisar** (`FEAT-MOD-011` `RN-8`, `RN-9`).
 *
 * Es el disparador exacto que las dos reglas piden, y no por casualidad: este
 * hecho se publica al crear un capítulo y en **cada** edición de su texto, y
 * nunca por otra cosa. Revisar aquí es revisar al publicar y en cada cambio,
 * y revisar **solo lo que cambia** — un capítulo, no la novela.
 *
 * Revisar solo al publicar dejaría abierto el esquive obvio: publicar un
 * texto inocuo y editarlo después.
 *
 * El hecho **no trae el texto**, y hace bien: una cola que persiste y
 * reintenta no es sitio para obra inédita. Se pide a `Work` por su contrato
 * en el momento de revisar.
 */
final readonly class ChapterContentUpdated implements IncomingIntegrationEvent
{
    private function __construct(
        public string $chapterId,
        public string $workId,
        public string $authorId,
        private string $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'ChapterContentUpdated';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $chapterId = $payload['chapterId'] ?? null;
        $workId = $payload['workId'] ?? null;
        $authorId = $payload['authorId'] ?? null;

        if (!\is_string($chapterId) || !\is_string($workId) || !\is_string($authorId)) {
            throw new \InvalidArgumentException('ChapterContentUpdated carries no chapter, work or author.');
        }

        return new self($chapterId, $workId, $authorId, $eventId, $occurredAt);
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
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'chapterId' => $this->chapterId,
            'workId' => $this->workId,
            'authorId' => $this->authorId,
            'occurredAt' => $this->occurredAt->format(\DATE_ATOM),
        ];
    }
}
