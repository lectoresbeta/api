<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `BetaReaderInvited`, tal y como lo modela `Notification`.
 *
 * Le han ofrecido una obra. El aviso es **para el invitado**, que no sabía
 * nada.
 */
final readonly class BetaReaderInvited implements IncomingIntegrationEvent
{
    private function __construct(
        public string $invitationId,
        public string $workId,
        public string $authorId,
        public string $readerId,
        private string $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'BetaReaderInvited';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        return new self(
            self::text($payload, 'invitationId'),
            self::text($payload, 'workId'),
            self::text($payload, 'authorId'),
            self::text($payload, 'readerId'),
            $eventId,
            $occurredAt,
        );
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
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'invitationId' => $this->invitationId,
            'workId' => $this->workId,
            'authorId' => $this->authorId,
            'readerId' => $this->readerId,
            'occurredAt' => $this->occurredAt->format(\DATE_ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function text(array $payload, string $field): string
    {
        $value = $payload[$field] ?? null;

        if (!\is_string($value) || '' === $value) {
            throw new \InvalidArgumentException(\sprintf('%s carries no %s.', self::subscribesTo(), $field));
        }

        return $value;
    }
}
