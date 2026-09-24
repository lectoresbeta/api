<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `FeedbackSubmitted`, tal y como lo modela `Notification`.
 *
 * Alguien ha corregido una obra. El aviso es **para su autor**, que es
 * quien lo estaba esperando.
 *
 * Nunca lleva el texto de la corrección (`RN-4`): un aviso dice que hay
 * algo que leer, y se lee dentro.
 */
final readonly class FeedbackSubmitted implements IncomingIntegrationEvent
{
    private function __construct(
        public string $correctionId,
        public string $workId,
        public string $chapterId,
        public string $authorId,
        public string $readerId,
        private string $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'FeedbackSubmitted';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        return new self(
            self::text($payload, 'correctionId'),
            self::text($payload, 'workId'),
            self::text($payload, 'chapterId'),
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
            'correctionId' => $this->correctionId,
            'workId' => $this->workId,
            'chapterId' => $this->chapterId,
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
