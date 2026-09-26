<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `ChapterRemoved`, tal y como lo modela `Credits` (`FEAT-WRK-003`).
 *
 * Un capítulo deja de existir, así que su precio deja de tener sentido — y el
 * de los que quedan puede cambiar, porque el final de la obra se ha movido.
 *
 * Llega con el orden que queda, así que aplicarlo dos veces da el mismo
 * resultado.
 */
final readonly class ChapterRemoved implements IncomingIntegrationEvent
{
    /**
     * @param list<string> $order
     */
    private function __construct(
        public string $workId,
        public string $chapterId,
        public array $order,
        private string $eventId,
        private \DateTimeImmutable $removedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'ChapterRemoved';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $workId = $payload['workId'] ?? null;
        $chapterId = $payload['chapterId'] ?? null;
        $order = $payload['order'] ?? null;

        if (!\is_string($workId) || !\is_string($chapterId) || !\is_array($order)) {
            throw new \InvalidArgumentException('ChapterRemoved carries no work, chapter or order.');
        }

        return new self(
            $workId,
            $chapterId,
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
        return $this->removedAt;
    }

    public function payload(): array
    {
        return [
            'workId' => $this->workId,
            'chapterId' => $this->chapterId,
            'order' => $this->order,
            'removedAt' => $this->removedAt->format(\DATE_ATOM),
        ];
    }
}
