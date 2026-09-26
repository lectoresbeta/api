<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `PlatformInvitationSent`, tal y como lo modela `Notification`
 * (`FEAT-NOT-007`).
 *
 * **Sin el token.** El enlace se pide en el momento de enviar el correo, por
 * `InvitationLinkProvider`: una credencial viva no entra en una cola que
 * persiste, reintenta y aparca mensajes.
 *
 * Trae la dirección porque sin ella no hay a quién escribir, igual que
 * `UserRegistered`.
 */
final readonly class PlatformInvitationSent implements IncomingIntegrationEvent
{
    private function __construct(
        public string $invitationId,
        public string $inviterId,
        public string $email,
        private string $eventId,
        private \DateTimeImmutable $sentAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'PlatformInvitationSent';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $invitationId = $payload['invitationId'] ?? null;
        $inviterId = $payload['inviterId'] ?? null;
        $email = $payload['email'] ?? null;

        if (!\is_string($invitationId) || !\is_string($inviterId) || !\is_string($email)) {
            throw new \InvalidArgumentException('PlatformInvitationSent carries no invitation, inviter or address.');
        }

        return new self($invitationId, $inviterId, $email, $eventId, $occurredAt);
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
        return $this->sentAt;
    }

    public function payload(): array
    {
        return [
            'invitationId' => $this->invitationId,
            'inviterId' => $this->inviterId,
            'email' => $this->email,
            'sentAt' => $this->sentAt->format(\DATE_ATOM),
        ];
    }
}
