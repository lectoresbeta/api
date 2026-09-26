<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\Event;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * `ContentReviewFlagged`, tal y como lo modela `Work`: **ese capítulo no se
 * enseña** (`FEAT-MOD-011` `RN-4`).
 *
 * No trae el texto ni hace falta: lo que hace este contexto es retirarlo, y
 * para eso basta con saber cuál.
 *
 * La decisión de qué significa «marcado» es de aquí y no del revisor. `Work`
 * lo trata como un bloqueo de moderación —el mismo camino que una reclamación
 * estimada—, y por tanto **no lo puede deshacer ninguna transición
 * ordinaria**: hace falta un moderador. Esa es la contrapartida de retirar
 * algo automáticamente, y es deliberada: lo que una máquina esconde, lo
 * devuelve una persona.
 */
final readonly class ContentReviewFlagged implements IncomingIntegrationEvent
{
    private function __construct(
        public string $chapterId,
        public string $workId,
        public string $reason,
        private string $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public static function subscribesTo(): string
    {
        return 'ContentReviewFlagged';
    }

    public static function fromPayload(string $eventId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $chapterId = $payload['chapterId'] ?? null;
        $workId = $payload['workId'] ?? null;
        $reason = $payload['reason'] ?? null;

        if (!\is_string($chapterId) || !\is_string($workId)) {
            throw new \InvalidArgumentException('ContentReviewFlagged carries no chapter or work.');
        }

        return new self(
            $chapterId,
            $workId,
            \is_string($reason) ? $reason : '',
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
            'chapterId' => $this->chapterId,
            'workId' => $this->workId,
            'reason' => $this->reason,
            'occurredAt' => $this->occurredAt->format(\DATE_ATOM),
        ];
    }
}
