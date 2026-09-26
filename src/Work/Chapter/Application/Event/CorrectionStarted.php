<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `CorrectionStarted`, tal y como lo modela `Work` (`FEAT-WRK-005` `RN-3`).
 *
 * Aquí este hecho significa una cosa y solo una: **alguien ha empezado a leer
 * el texto que hay ahora**, así que sobrescribirlo dejaría de ser gratis.
 *
 * Por eso lo escucha `Work` y no hace falta preguntarle nada a `Feedback`: el
 * hecho ya viaja, y lo que este contexto necesita saber —que esta versión
 * tiene ojos encima— está entero en él.
 */
final readonly class CorrectionStarted implements IncomingIntegrationEvent
{
    private function __construct(
        public string $chapterId,
        public string $workId,
        private string $eventId,
        private \DateTimeImmutable $startedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'CorrectionStarted';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $chapterId = $payload['chapterId'] ?? null;
        $workId = $payload['workId'] ?? null;

        if (!\is_string($chapterId) || !\is_string($workId)) {
            throw new \InvalidArgumentException('CorrectionStarted carries no chapter or work.');
        }

        return new self($chapterId, $workId, $eventId, $occurredAt);
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
        return $this->startedAt;
    }

    public function payload(): array
    {
        return [
            'chapterId' => $this->chapterId,
            'workId' => $this->workId,
            'startedAt' => $this->startedAt->format(\DATE_ATOM),
        ];
    }
}
