<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `WorkRated`, tal y como lo modela `Work` (`FEAT-WRK-015`).
 *
 * De todo lo que trae, aquí importan tres cosas: qué obra, quién y qué nota.
 * `firstTime` **no se usa**, y es deliberado: decirlo es asunto de quien lo
 * publica, y este contexto lo sabe mejor por sí mismo —mira si ya tenía una
 * nota de esa persona— que fiándose de una bandera que una reentrega
 * repetiría.
 */
final readonly class WorkRated implements IncomingIntegrationEvent
{
    private function __construct(
        public string $workId,
        public string $readerId,
        public int $rating,
        private string $eventId,
        private \DateTimeImmutable $ratedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'WorkRated';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $workId = $payload['workId'] ?? null;
        $readerId = $payload['readerId'] ?? null;
        $rating = $payload['rating'] ?? null;

        if (!\is_string($workId) || '' === $workId || !\is_string($readerId) || '' === $readerId || !\is_int($rating)) {
            throw new \InvalidArgumentException('WorkRated needs a work, a reader and a rating.');
        }

        return new self($workId, $readerId, $rating, $eventId, $occurredAt);
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
        return $this->ratedAt;
    }

    public function payload(): array
    {
        return [
            'workId' => $this->workId,
            'readerId' => $this->readerId,
            'rating' => $this->rating,
            'ratedAt' => $this->ratedAt->format(\DATE_ATOM),
        ];
    }
}
