<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `CorrectionDraftDiscarded`, as `Credits` models it (`FEAT-CRD-009`).
 *
 * The reader gave up. There is **nothing to release** — no credit was ever
 * set aside — so all this does is drop the quotation and free one of the
 * three slots the chapter has.
 */
final readonly class CorrectionDraftDiscarded implements IncomingIntegrationEvent
{
    private function __construct(
        public string $chapterId,
        public string $readerId,
        private string $eventId,
        private \DateTimeImmutable $discardedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'CorrectionDraftDiscarded';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $chapterId = $payload['chapterId'] ?? null;
        $readerId = $payload['readerId'] ?? null;

        if (!\is_string($chapterId) || !\is_string($readerId)) {
            throw new \InvalidArgumentException('CorrectionDraftDiscarded carries no chapter or reader.');
        }

        return new self($chapterId, $readerId, $eventId, $occurredAt);
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
        return $this->discardedAt;
    }

    public function payload(): array
    {
        return [
            'chapterId' => $this->chapterId,
            'readerId' => $this->readerId,
            'discardedAt' => $this->discardedAt->format(\DATE_ATOM),
        ];
    }
}
