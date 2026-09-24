<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `ChapterCorrectabilityChanged`, as `Feedback` models it (`FEAT-CRD-009`).
 *
 * **Un booleano, y este contexto no quiere nada más.** El hecho trae también
 * cuántas correcciones puede pagar el autor, que el catálogo usa para
 * ordenar; aquí no se lee siquiera. Abrir el panel es sí o no.
 *
 * Es lo que `decision:0013` permite: un consumidor declara lo que necesita,
 * no lo que el publicador manda.
 */
final readonly class ChapterCorrectabilityChanged implements IncomingIntegrationEvent
{
    private function __construct(
        public string $chapterId,
        public string $workId,
        public bool $correctable,
        private string $eventId,
        private \DateTimeImmutable $changedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'ChapterCorrectabilityChanged';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $chapterId = $payload['chapterId'] ?? null;
        $workId = $payload['workId'] ?? null;
        $correctable = $payload['correctable'] ?? null;

        if (!\is_string($chapterId) || !\is_string($workId)) {
            throw new \InvalidArgumentException('ChapterCorrectabilityChanged carries no chapter or work.');
        }

        if (!\is_bool($correctable)) {
            throw new \InvalidArgumentException('ChapterCorrectabilityChanged carries no answer.');
        }

        return new self($chapterId, $workId, $correctable, $eventId, $occurredAt);
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
        return $this->changedAt;
    }

    public function payload(): array
    {
        return [
            'chapterId' => $this->chapterId,
            'workId' => $this->workId,
            'correctable' => $this->correctable,
            'changedAt' => $this->changedAt->format(\DATE_ATOM),
        ];
    }
}
