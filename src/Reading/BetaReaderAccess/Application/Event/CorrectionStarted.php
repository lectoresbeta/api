<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderAccess\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `CorrectionStarted`, tal y como lo modela `Reading` (`FEAT-RDG-001`).
 *
 * Una clase distinta de la que publica `Feedback`, a propósito
 * ([`decision:0013`](../../../../../docs/decisions/0013-integration-events-travel-without-class-names.md)):
 * lo único que comparten los dos lados es el nombre del hecho y la forma de
 * su payload.
 *
 * Este contexto lee cuatro campos y ninguno es la modalidad de la obra, que
 * deliberadamente no comprueba (`RN-7`).
 */
final readonly class CorrectionStarted implements IncomingIntegrationEvent
{
    private function __construct(
        public string $chapterId,
        public string $workId,
        public string $authorId,
        public string $readerId,
        private string $eventId,
        private \DateTimeImmutable $startedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'CorrectionStarted';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $chapterId = $payload['chapterId'] ?? null;
        $workId = $payload['workId'] ?? null;
        $authorId = $payload['authorId'] ?? null;
        $readerId = $payload['readerId'] ?? null;

        if (!\is_string($chapterId) || !\is_string($workId) || !\is_string($authorId) || !\is_string($readerId)) {
            throw new \InvalidArgumentException('CorrectionStarted is missing one of its four identifiers.');
        }

        return new self($chapterId, $workId, $authorId, $readerId, $eventId, $occurredAt);
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
        return $this->startedAt;
    }

    public function payload(): array
    {
        return [
            'chapterId' => $this->chapterId,
            'workId' => $this->workId,
            'authorId' => $this->authorId,
            'readerId' => $this->readerId,
            'startedAt' => $this->startedAt->format(\DATE_ATOM),
        ];
    }
}
