<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `WorkOpenedForCorrection`, tal y como lo modela `Credits` (`FEAT-WRK-016`).
 *
 * De todo lo que trae importan dos cosas: **de qué obra habla y en qué
 * versión de su estado**. Lo que significa para la corregibilidad lo decide
 * este contexto.
 *
 * La versión no es un detalle de transporte: sin ella, las reentregas de este
 * hecho y de su contrario se turnarían para siempre (`FEAT-WRK-014` resolvió
 * lo mismo para el cuestionario).
 */
final readonly class WorkOpenedForCorrection implements IncomingIntegrationEvent
{
    private function __construct(
        public string $workId,
        public int $version,
        private string $eventId,
        private \DateTimeImmutable $openedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'WorkOpenedForCorrection';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $workId = $payload['workId'] ?? null;
        $version = $payload['version'] ?? null;

        if (!\is_string($workId) || '' === $workId || !\is_int($version)) {
            throw new \InvalidArgumentException('WorkOpenedForCorrection needs a work and a version.');
        }

        return new self($workId, $version, $eventId, $occurredAt);
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
        return $this->openedAt;
    }

    public function payload(): array
    {
        return [
            'workId' => $this->workId,
            'version' => $this->version,
            'openedAt' => $this->openedAt->format(\DATE_ATOM),
        ];
    }
}
