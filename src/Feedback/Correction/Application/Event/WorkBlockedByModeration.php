<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `WorkBlockedByModeration`, tal y como lo modela `Feedback` (`FEAT-MOD-003`
 * `RN-4`).
 *
 * Aquí significa que quien estuviera corrigiendo **pierde ese trabajo**. Es
 * inevitable; lo que no puede ser es que lo descubra al intentar entregar.
 */
final readonly class WorkBlockedByModeration implements IncomingIntegrationEvent
{
    private function __construct(
        public string $workId,
        public string $scope,
        public ?string $chapterId,
        private string $eventId,
        private \DateTimeImmutable $blockedAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'WorkBlockedByModeration';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $workId = $payload['workId'] ?? null;
        $scope = $payload['scope'] ?? null;
        $chapterId = $payload['chapterId'] ?? null;

        if (!\is_string($workId) || !\is_string($scope)) {
            throw new \InvalidArgumentException('WorkBlockedByModeration carries no work or scope.');
        }

        return new self($workId, $scope, \is_string($chapterId) ? $chapterId : null, $eventId, $occurredAt);
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
            'workId' => $this->workId,
            'scope' => $this->scope,
            'chapterId' => $this->chapterId,
            'blockedAt' => $this->blockedAt->format(\DATE_ATOM),
        ];
    }
}
