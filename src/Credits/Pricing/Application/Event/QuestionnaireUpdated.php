<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `QuestionnaireUpdated`, as `Credits` models it (`FEAT-CRD-016`).
 *
 * It reads three of the fields `Work` publishes and ignores `questionCount`,
 * which is exactly what `decision:0013` buys: a consumer declares what it
 * needs, not what the publisher happens to send.
 *
 * The two word totals are not a redundancy. The last chapter of a work
 * answers every question; the rest answer only those of scope
 * `EVERY_CHAPTER` (`FEAT-WRK-014` `W-17`), and `Credits` prices per chapter.
 */
final readonly class QuestionnaireUpdated implements IncomingIntegrationEvent
{
    private function __construct(
        public string $workId,
        public int $version,
        public int $requiredWords,
        public int $requiredWordsForEveryChapter,
        private string $eventId,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'QuestionnaireUpdated';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $workId = $payload['workId'] ?? null;
        $version = $payload['version'] ?? null;
        $requiredWords = $payload['requiredWords'] ?? null;
        $forEveryChapter = $payload['requiredWordsForEveryChapter'] ?? null;

        if (!\is_string($workId) || '' === $workId) {
            throw new \InvalidArgumentException('QuestionnaireUpdated carries no workId.');
        }

        if (!\is_int($version) || $version < 1) {
            throw new \InvalidArgumentException('QuestionnaireUpdated carries no version.');
        }

        if (!\is_int($requiredWords) || !\is_int($forEveryChapter) || $requiredWords < 0 || $forEveryChapter < 0) {
            throw new \InvalidArgumentException('QuestionnaireUpdated carries no usable word demand.');
        }

        return new self($workId, $version, $requiredWords, $forEveryChapter, $eventId, $occurredAt);
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
        return $this->updatedAt;
    }

    public function payload(): array
    {
        return [
            'workId' => $this->workId,
            'version' => $this->version,
            'requiredWords' => $this->requiredWords,
            'requiredWordsForEveryChapter' => $this->requiredWordsForEveryChapter,
            'updatedAt' => $this->updatedAt->format(\DATE_ATOM),
        ];
    }
}
