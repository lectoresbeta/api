<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\ContentReview\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * El revisor automático ha aprobado un texto (`FEAT-MOD-011`).
 *
 * **Sin una palabra del texto**, como todo lo que sale de aquí: lo revisado
 * es obra inédita y una cola que persiste, reintenta y aparca mensajes no es
 * sitio para ella.
 *
 * Lleva la versión del revisor porque el hecho no es «esto está bien» sino
 * «esto pasó **estas** reglas», y las reglas van a cambiar.
 */
final readonly class ContentReviewPassed implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private string $chapterId,
        private string $workId,
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
        return 'ContentReviewPassed';
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
            'reviewerVersion' => $this->reviewerVersion,
            'reviewedAt' => $this->reviewedAt->format(\DATE_ATOM),
        ];
    }
}
