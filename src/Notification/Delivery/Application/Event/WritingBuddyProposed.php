<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `WritingBuddyProposed`, tal y como lo modela `Notification`
 * (`FEAT-RDG-008`).
 *
 * `WRITING_BUDDY_PROPOSED` llevaba en el catálogo desde `FEAT-NOT-001` con su
 * frase escrita y sin nadie que lo disparara. Esto lo enciende, y con ello
 * `FEAT-NOT-005` queda completa: solicitudes, invitaciones **y propuestas**.
 */
final readonly class WritingBuddyProposed implements IncomingIntegrationEvent
{
    private function __construct(
        public string $linkId,
        public string $proposerId,
        public string $partnerId,
        private string $eventId,
        private \DateTimeImmutable $proposedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'WritingBuddyProposed';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $linkId = $payload['linkId'] ?? null;
        $proposerId = $payload['proposerId'] ?? null;
        $partnerId = $payload['partnerId'] ?? null;

        if (!\is_string($linkId) || !\is_string($proposerId) || !\is_string($partnerId)) {
            throw new \InvalidArgumentException('WritingBuddyProposed carries no link or people.');
        }

        return new self($linkId, $proposerId, $partnerId, $eventId, $occurredAt);
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
        return $this->proposedAt;
    }

    public function payload(): array
    {
        return [
            'linkId' => $this->linkId,
            'proposerId' => $this->proposerId,
            'partnerId' => $this->partnerId,
            'proposedAt' => $this->proposedAt->format(\DATE_ATOM),
        ];
    }
}
