<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Domain\Event;

use LectoresBeta\Credits\Pricing\Domain\ValueObject\ChapterId;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * A chapter has started, or stopped, admitting corrections
 * (`FEAT-CRD-009`).
 *
 * **Ni un saldo ni un precio.** Lleva la conclusión de este contexto —si se
 * puede corregir, y cuánto trabajo produciría enseñarlo— y nunca las dos
 * cifras con las que se calcula:
 * `Feedback` opens the correction panel against its own projection of this
 * fact, with no round trip and no idea what anything costs.
 *
 * It is a derived fact rather than a question anyone asks, which is what
 * lets that panel open instantly. A projection slightly behind can only
 * produce an overdraft, and an overdraft is already an accepted outcome
 * (`RN-5`).
 */
final readonly class ChapterCorrectabilityChanged implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private ChapterId $chapterId,
        private WorkId $workId,
        private bool $correctable,
        private int $affordableCorrections,
        private \DateTimeImmutable $changedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'ChapterCorrectabilityChanged';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->changedAt;
    }

    public function payload(): array
    {
        return [
            'chapterId' => $this->chapterId->value(),
            'workId' => $this->workId->value(),
            'correctable' => $this->correctable,
            // Cuántas correcciones de este capítulo puede pagar su autor,
            // con tope de diez. **No es un saldo ni un precio**: es lo que el
            // catálogo necesita para ordenar por trabajo producido
            // (`decision:0008`), y lo único de esa aritmética que sale de
            // aquí.
            'affordableCorrections' => $this->affordableCorrections,
            'changedAt' => $this->changedAt->format(\DATE_ATOM),
        ];
    }
}
