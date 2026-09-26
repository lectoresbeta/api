<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderAccess\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `CorrectionResumed`, tal y como lo modela `Reading` (`R-22`).
 *
 * Alguien vuelve a abrir el panel de una corrección que ya tenía empezada.
 * Este contexto lo trata **igual que un comienzo**: si esa persona no tiene
 * acceso vivo a la obra, se lo concede.
 *
 * Que el hecho llegue ya significa que puede corregir ahora mismo —`Feedback`
 * comprueba la elegibilidad antes de publicarlo, y rechaza a quien perdió el
 * acceso a una obra cerrada—, así que aquí tampoco hace falta mirar la
 * modalidad de la obra. Es el mismo razonamiento que con `CorrectionStarted`,
 * y la misma razón por la que este contexto no guarda una copia de la
 * modalidad de cada obra.
 *
 * Lee los mismos cuatro campos que un comienzo. `correctionId` viaja en el
 * payload y aquí no se usa: la idempotencia la da el identificador del hecho.
 */
final readonly class CorrectionResumed implements IncomingIntegrationEvent
{
    private function __construct(
        public string $chapterId,
        public string $workId,
        public string $authorId,
        public string $readerId,
        private string $eventId,
        private \DateTimeImmutable $resumedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'CorrectionResumed';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $chapterId = $payload['chapterId'] ?? null;
        $workId = $payload['workId'] ?? null;
        $authorId = $payload['authorId'] ?? null;
        $readerId = $payload['readerId'] ?? null;

        if (!\is_string($chapterId) || !\is_string($workId) || !\is_string($authorId) || !\is_string($readerId)) {
            throw new \InvalidArgumentException('CorrectionResumed is missing one of its four identifiers.');
        }

        return new self($chapterId, $workId, $authorId, $readerId, $eventId, $occurredAt);
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
        return $this->resumedAt;
    }

    public function payload(): array
    {
        return [
            'chapterId' => $this->chapterId,
            'workId' => $this->workId,
            'authorId' => $this->authorId,
            'readerId' => $this->readerId,
            'resumedAt' => $this->resumedAt->format(\DATE_ATOM),
        ];
    }
}
