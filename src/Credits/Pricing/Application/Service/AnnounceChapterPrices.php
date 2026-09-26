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
 *
 * **El precio anterior lo trae quien llama**, y tiene que ser una foto tomada
 * *antes* de repreciar: `ChapterPrice` se modifica en el sitio, así que para
 * cuando esto se ejecuta la entidad ya no recuerda de dónde venía. Sin esa
 * foto no se puede distinguir un encarecimiento de un abaratamiento, y el
 * aviso de `FEAT-CRD-016` `RN-9` saldría en los dos casos.
 */
final readonly class AnnounceChapterPrices
{
    public function __construct(private EventPublisher $events)
    {
    }

    /**
     * La foto del antes, tomada **antes de tocar nada**.
     *
     * Es un método de esta clase y no una línea suelta en cada consumidor
     * porque los tres repreciadores necesitan exactamente lo mismo, y el que
     * se olvidara de tomarla publicaría encarecimientos que no lo son.
     *
     * @param iterable<ChapterPrice> $chapters
     *
     * @return array<string, int>
     */
    public static function snapshot(iterable $chapters): array
    {
        $before = [];

        foreach ($chapters as $chapter) {
            $before[$chapter->chapterId()->value()] = $chapter->price();
        }

        return $before;
    }

    /**
     * @param list<ChapterPrice> $chapters
     * @param array<string, int> $previousPrices lo que valía cada capítulo
     *                                           **antes** de repreciar,
     *                                           indexado por identificador.
     *                                           El que no esté estrena precio
     */
    public function of(array $chapters, array $previousPrices, \DateTimeImmutable $now): void
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
                $previousPrices[$chapter->chapterId()->value()] ?? null,
                $now,
            ),
            $chapters,
        ));
    }
}
