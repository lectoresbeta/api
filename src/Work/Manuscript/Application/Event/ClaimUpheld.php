<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `ClaimUpheld`, tal y como lo modela `Work` (`FEAT-MOD-003`).
 *
 * Llega **sin instrucciones**: dice que se ha estimado una reclamación y
 * sobre qué, no «bloquea esta obra». Quién ejecuta el bloqueo es este
 * contexto, porque es un cambio en el ciclo de vida de la obra y ese es su
 * modelo. Si `Moderation` escribiese el estado, tendría que conocer las
 * transiciones de `Work` y dos contextos gobernarían el mismo dato.
 *
 * Tampoco llega la motivación del moderador, y no hace falta: lo que el autor
 * tiene que saber es **qué** se ha estimado, que es el tipo de la
 * reclamación.
 */
final readonly class ClaimUpheld implements IncomingIntegrationEvent
{
    private function __construct(
        public string $claimId,
        public string $type,
        public string $targetType,
        public string $targetId,
        private string $eventId,
        private \DateTimeImmutable $upheldAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'ClaimUpheld';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $claimId = $payload['claimId'] ?? null;
        $type = $payload['type'] ?? null;
        $targetType = $payload['targetType'] ?? null;
        $targetId = $payload['targetId'] ?? null;

        if (!\is_string($claimId) || !\is_string($type) || !\is_string($targetType) || !\is_string($targetId)) {
            throw new \InvalidArgumentException('ClaimUpheld carries no claim, type or target.');
        }

        return new self($claimId, $type, $targetType, $targetId, $eventId, $occurredAt);
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
        return $this->upheldAt;
    }

    public function payload(): array
    {
        return [
            'claimId' => $this->claimId,
            'type' => $this->type,
            'targetType' => $this->targetType,
            'targetId' => $this->targetId,
            'upheldAt' => $this->upheldAt->format(\DATE_ATOM),
        ];
    }
}
