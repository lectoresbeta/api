<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Relationship\Domain\Event;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * Alguien ha bloqueado a alguien (`FEAT-COM-034` `RN-10`).
 *
 * **`Community` publica el hecho y no hace nada más**: no revoca accesos de
 * lector beta y no toca créditos. Eso es lo que mantiene la arquitectura en
 * pie — este contexto conoce relaciones sociales, no obras ni saldos, y cada
 * uno decide qué significa un bloqueo en su modelo.
 *
 * El hecho es **unilateral en la intención**: dice quién bloqueó a quién. Que
 * el efecto sea bidireccional lo decide cada consumidor, porque cada uno lo
 * aplica sobre cosas distintas.
 */
final readonly class UserBlocked implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private MemberId $blockerId,
        private MemberId $blockedId,
        private \DateTimeImmutable $blockedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'UserBlocked';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->blockedAt;
    }

    public function payload(): array
    {
        return [
            'blockerId' => $this->blockerId->value(),
            'blockedId' => $this->blockedId->value(),
            'blockedAt' => $this->blockedAt->format(\DATE_ATOM),
        ];
    }
}
