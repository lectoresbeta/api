<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Catalogue\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `ChapterCorrectabilityChanged`, tal y como lo modela `Work`
 * (`FEAT-WRK-012`).
 *
 * De los dos datos que trae, el catálogo usa **los dos**: el booleano es el
 * filtro duro —solo entra lo que se puede corregir ahora— y
 * `affordableCorrections` es el primer factor de la puntuación
 * ([`decision:0008`](../../../../../docs/decisions/0008-catalogue-ordering.md)).
 *
 * Ninguno es dinero. `Work` sigue sin saber lo que cuesta corregir nada, que
 * es lo que permite ordenar por capacidad de pago sin tocar el modelo de
 * `Credits`.
 */
final readonly class ChapterCorrectabilityChanged implements IncomingIntegrationEvent
{
    private function __construct(
        public string $chapterId,
        public string $workId,
        public bool $correctable,
        public int $affordableCorrections,
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
        $affordable = $payload['affordableCorrections'] ?? null;

        if (!\is_string($chapterId) || !\is_string($workId) || !\is_bool($correctable)) {
            throw new \InvalidArgumentException('ChapterCorrectabilityChanged carries no chapter, work or answer.');
        }

        return new self(
            $chapterId,
            $workId,
            $correctable,
            // Un hecho anterior a que se publicara la capacidad ordena como
            // si fuera cero, que es lo mismo que le pasa a una obra que nadie
            // puede pagar: abajo del todo, pero sin romperse.
            \is_int($affordable) ? max(0, $affordable) : 0,
            $eventId,
            $occurredAt,
        );
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
            'affordableCorrections' => $this->affordableCorrections,
            'changedAt' => $this->changedAt->format(\DATE_ATOM),
        ];
    }
}
