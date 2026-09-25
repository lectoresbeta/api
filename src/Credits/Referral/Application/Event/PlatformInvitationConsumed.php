<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Referral\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `PlatformInvitationConsumed`, tal y como lo modela `Credits`
 * (`FEAT-CRD-005`).
 *
 * El hecho trae tres identificadores y este contexto se queda con dos. El de
 * la invitación se descarta a propósito: aquí la unidad es **la persona
 * invitada**, no el papel con el que llegó, y guardarlo invitaría a razonar
 * sobre invitaciones —caducidad, reenvíos, tokens— que son asunto de `User`.
 *
 * Y no trae importe, porque no lo trae ningún hecho que entra aquí: cuánto
 * vale traer a alguien lo decide `Credits` y nadie más (`decision:0002`).
 */
final readonly class PlatformInvitationConsumed implements IncomingIntegrationEvent
{
    private function __construct(
        public string $inviterId,
        public string $inviteeId,
        private string $eventId,
        private \DateTimeImmutable $consumedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'PlatformInvitationConsumed';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $inviterId = $payload['inviterId'] ?? null;
        $inviteeId = $payload['inviteeId'] ?? null;

        if (!\is_string($inviterId) || !\is_string($inviteeId)) {
            throw new \InvalidArgumentException('PlatformInvitationConsumed is missing one of the two parties.');
        }

        return new self($inviterId, $inviteeId, $eventId, $occurredAt);
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
        return $this->consumedAt;
    }

    public function payload(): array
    {
        return [
            'inviterId' => $this->inviterId,
            'inviteeId' => $this->inviteeId,
            'consumedAt' => $this->consumedAt->format(\DATE_ATOM),
        ];
    }
}
