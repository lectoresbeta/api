<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `ChaptersReordered`, tal y como lo modela `Credits` (`FEAT-WRK-003`).
 *
 * Aquí este hecho no es cosmético: **el último capítulo es el que responde
 * las preguntas de alcance `LAST_CHAPTER`**, así que reordenar cambia lo que
 * cuesta corregir dos capítulos — el que deja de ser el último y el que pasa
 * a serlo.
 *
 * Llega con el orden entero, así que aplicarlo dos veces da el mismo
 * resultado. Es lo único que sobrevive a una cola que entrega al menos una
 * vez.
 */
final readonly class ChaptersReordered implements IncomingIntegrationEvent
{
    /**
     * @param list<string> $order
     */
    private function __construct(
        public string $workId,
        public array $order,
        private string $eventId,
        private \DateTimeImmutable $reorderedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'ChaptersReordered';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $workId = $payload['workId'] ?? null;
        $order = $payload['order'] ?? null;

        if (!\is_string($workId) || !\is_array($order)) {
            throw new \InvalidArgumentException('ChaptersReordered carries no work or order.');
        }

        return new self(
            $workId,
            array_values(array_filter($order, \is_string(...))),
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
        return $this->reorderedAt;
    }

    public function payload(): array
    {
        return [
            'workId' => $this->workId,
            'order' => $this->order,
            'reorderedAt' => $this->reorderedAt->format(\DATE_ATOM),
        ];
    }
}
