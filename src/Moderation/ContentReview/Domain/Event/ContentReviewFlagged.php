<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\ContentReview\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * El revisor automático ha marcado un texto (`FEAT-MOD-011` `RN-4`).
 *
 * Lo consume `Work`, que **retira el capítulo**, y lo acompaña una
 * reclamación automática con reclamante `SYSTEM` para que lo mire un humano.
 *
 * **Marcar no es sancionar** (`RN-5`), y esa es la regla que conviene no
 * relajar nunca: lo que se retira es el texto, no se toca la cuenta de nadie.
 * Un sistema automático que sanciona sin intervención se equivoca en silencio
 * y a escala, y el afectado no tiene con quién hablar.
 *
 * `reason` es el motivo que dio el revisor, no el texto revisado.
 */
final readonly class ContentReviewFlagged implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private string $chapterId,
        private string $workId,
        private string $authorId,
        private string $reason,
        private string $reviewerVersion,
        private \DateTimeImmutable $reviewedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'ContentReviewFlagged';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->reviewedAt;
    }

    public function payload(): array
    {
        return [
            'chapterId' => $this->chapterId,
            'workId' => $this->workId,
            'authorId' => $this->authorId,
            'reason' => $this->reason,
            'reviewerVersion' => $this->reviewerVersion,
            'reviewedAt' => $this->reviewedAt->format(\DATE_ATOM),
        ];
    }
}
