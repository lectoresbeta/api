<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `ClaimUpheld`, tal y como lo modela `Credits` (`FEAT-MOD-002`).
 *
 * Llega **sin importes y sin instrucciones**: dice que se ha estimado una
 * reclamación sobre algo, y este contexto decide solo qué significa eso para
 * el saldo. Es la regla dura de `AGENTS.md` —nadie llama a `Credits` para que
 * mueva créditos— aplicada al único caso donde una decisión humana los mueve.
 *
 * Lo que este contexto hace con ella está en su propio sitio: si la
 * reclamación era sobre una corrección, se revierte lo que se cobró por ella.
 * Cuánto era, lo sabe `Credits` porque lo apuntó.
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
