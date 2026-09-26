<?php

declare(strict_types=1);

namespace LectoresBeta\User\Privacy\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `UserBlocked`, tal y como lo modela `User`.
 *
 * Una clase distinta de la que publica `Community`, como siempre
 * ([`decision:0013`](../../../../../docs/decisions/0013-integration-events-travel-without-class-names.md)).
 *
 * `User` lo consume para una sola cosa: que un bloqueo **venza a cualquier
 * ajuste de privacidad** al decidir quién puede comentar los textos de quién
 * (`FEAT-USR-038` `RN-5`).
 */
final readonly class UserBlocked implements IncomingIntegrationEvent
{
    private function __construct(
        public string $blockerId,
        public string $blockedId,
        private string $eventId,
        private \DateTimeImmutable $blockedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'UserBlocked';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $blockerId = $payload['blockerId'] ?? null;
        $blockedId = $payload['blockedId'] ?? null;

        if (!\is_string($blockerId) || '' === $blockerId || !\is_string($blockedId) || '' === $blockedId) {
            throw new \InvalidArgumentException('UserBlocked needs both identifiers.');
        }

        return new self($blockerId, $blockedId, $eventId, $occurredAt);
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
        return $this->blockedAt;
    }

    public function payload(): array
    {
        return [
            'blockerId' => $this->blockerId,
            'blockedId' => $this->blockedId,
            'blockedAt' => $this->blockedAt->format(\DATE_ATOM),
        ];
    }
}
