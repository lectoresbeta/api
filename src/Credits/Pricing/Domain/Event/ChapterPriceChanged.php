<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Domain\Event;

use LectoresBeta\Credits\Pricing\Domain\ValueObject\ChapterId;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * Corregir este capítulo vale otra cifra (`FEAT-CRD-013`).
 *
 * **Sí lleva un importe, y es el único de este contexto que lo hace hacia
 * fuera sin hablar de una cuenta.** No contradice la regla de aislamiento,
 * que dice lo contrario de lo que parece: lo prohibido es que otro contexto
 * **calcule** efectos de crédito
 * ([`decision:0002`](../../../../../docs/decisions/0002-credits-as-isolated-bounded-context.md)),
 * no que `Credits` publique lo que ha decidido. `FEAT-CRD-013` `RN-1` lo pide
 * con todas las letras: la traducción a créditos es de `Credits` y de nadie
 * más, y por eso la cifra viaja ya traducida.
 *
 * Lo que la insignia enseña es **lo que gana quien corrige**. Y como una
 * corrección es una transferencia, es también lo que paga el autor: la
 * ambigüedad que arrastraba `H-1` —recompensa o coste— desaparece porque son
 * el mismo número.
 *
 * No lleva saldos. Que el autor pueda pagarlo se dice aparte, en
 * `ChapterCorrectabilityChanged`, y es otra pregunta.
 */
final readonly class ChapterPriceChanged implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private ChapterId $chapterId,
        private WorkId $workId,
        private int $credits,
        private \DateTimeImmutable $changedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'ChapterPriceChanged';
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
            'credits' => $this->credits,
            'changedAt' => $this->changedAt->format(\DATE_ATOM),
        ];
    }
}
