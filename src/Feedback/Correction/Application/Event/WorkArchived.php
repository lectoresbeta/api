<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `WorkArchived`, tal y como lo modela `Feedback` (`FEAT-WRK-006` `RN-4`).
 *
 * El autor ha retirado su obra, y quien estuviera corrigiéndola tiene que
 * enterarse por un aviso y no por un error al entregar.
 */
final readonly class WorkArchived implements IncomingIntegrationEvent
{
    private function __construct(
        public string $workId,
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

        if (!\is_string($workId)) {
            throw new \InvalidArgumentException('WorkArchived carries no work.');
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
        return $this->archivedAt;
    }

    public function payload(): array
    {
        return ['workId' => $this->workId, 'archivedAt' => $this->archivedAt->format(\DATE_ATOM)];
    }
}
