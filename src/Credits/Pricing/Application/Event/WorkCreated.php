<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `WorkCreated`, tal y como lo modela `Credits` (`FEAT-WRK-016`).
 *
 * De todo lo que trae, aquí importa **una sola cosa**: de qué obra habla. Lo
 * que significa para la corregibilidad lo decide este contexto.
 */
final readonly class WorkCreated implements IncomingIntegrationEvent
{
    private function __construct(
        public string $workId,
        private string $eventId,
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'WorkCreated';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $workId = $payload['workId'] ?? null;

        if (!\is_string($workId) || '' === $workId) {
            throw new \InvalidArgumentException('WorkCreated carries no workId.');
        }

        return new self($workId, $eventId, $occurredAt);
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
        return $this->createdAt;
    }

    public function payload(): array
    {
        return [
            'workId' => $this->workId,
            'createdAt' => $this->createdAt->format(\DATE_ATOM),
        ];
    }
}
