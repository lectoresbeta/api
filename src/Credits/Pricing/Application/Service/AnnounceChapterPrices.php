<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Application\Service;

use LectoresBeta\Credits\Pricing\Domain\Entity\ChapterPrice;
use LectoresBeta\Credits\Pricing\Domain\Event\ChapterPriceChanged;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Event\EventId;

/**
 * Decir cuánto vale corregir un capítulo (`FEAT-CRD-013`).
 *
 * Se publica **solo cuando la cifra cambia**, no en cada recálculo: el precio
 * se recalcula cada vez que la obra crece o el cuestionario se toca, y casi
 * siempre sale el mismo número.
 *
 * Es la vía que la ficha eligió —proyección por eventos, no consulta
 * síncrona— porque la insignia se pinta en pantallas que listan decenas de
 * obras y una llamada por tarjeta las haría lentas para enseñar un número que
 * cambia poco.
 */
final readonly class AnnounceChapterPrices
{
    public function __construct(private EventPublisher $events)
    {
    }

    /**
     * @param list<ChapterPrice> $chapters
     */
    public function of(array $chapters, \DateTimeImmutable $now): void
    {
        if ([] === $chapters) {
            return;
        }

        $this->events->publish(...array_map(
            static fn (ChapterPrice $chapter): ChapterPriceChanged => new ChapterPriceChanged(
                EventId::generate(),
                $chapter->chapterId(),
                $chapter->workId(),
                $chapter->price(),
                $now,
            ),
            $chapters,
        ));
    }
}
