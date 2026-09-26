<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `WorkBlockedByModeration`, tal y como lo modela `Notification`
 * (`FEAT-MOD-003` `RN-8b`).
 *
 * Trae lo justo para escribir el correo: **qué** se ha bloqueado y **por
 * qué**. La tercera cosa que el mensaje necesita —a qué dirección recurrir—
 * no viaja en el hecho porque no es del hecho: es configuración de la
 * plataforma, y ponerla en un evento la congelaría en cada mensaje
 * pendiente en la cola.
 *
 * No trae la motivación del moderador, que es material interno del
 * expediente.
 */
final readonly class WorkBlockedByModeration implements IncomingIntegrationEvent
{
    private function __construct(
        public string $workId,
        public string $authorId,
        public string $title,
        public string $scope,
        public ?string $chapterId,
        public string $reason,
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
        $authorId = $payload['authorId'] ?? null;
        $title = $payload['title'] ?? null;
        $scope = $payload['scope'] ?? null;
        $reason = $payload['reason'] ?? null;
        $chapterId = $payload['chapterId'] ?? null;

        if (!\is_string($workId) || !\is_string($authorId) || !\is_string($title)
            || !\is_string($scope) || !\is_string($reason)) {
            throw new \InvalidArgumentException('WorkBlockedByModeration carries no work, author or scope.');
        }

        return new self(
            $workId,
            $authorId,
            $title,
            $scope,
            \is_string($chapterId) ? $chapterId : null,
            $reason,
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
        return $this->blockedAt;
    }

    public function payload(): array
    {
        return [
            'workId' => $this->workId,
            'authorId' => $this->authorId,
            'title' => $this->title,
            'scope' => $this->scope,
            'chapterId' => $this->chapterId,
            'reason' => $this->reason,
            'blockedAt' => $this->blockedAt->format(\DATE_ATOM),
        ];
    }
}
