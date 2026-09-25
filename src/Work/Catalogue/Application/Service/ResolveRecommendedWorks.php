<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Catalogue\Application\Service;

use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Work\Catalogue\Application\Contract\RecommendedWork;
use LectoresBeta\Work\Catalogue\Application\Contract\RecommendedWorks;
use LectoresBeta\Work\Catalogue\Application\DTO\CatalogueCriteria;
use LectoresBeta\Work\Catalogue\Application\DTO\CatalogueEntry;
use LectoresBeta\Work\Catalogue\Application\Port\CatalogueQuery;
use LectoresBeta\Work\Chapter\Domain\Service\ReadingTime;
use LectoresBeta\Work\Manuscript\Domain\Enum\ContentWarning;
use LectoresBeta\Work\Manuscript\Domain\Enum\WorkStatus;

/**
 * El lado de `Work` en el carrusel de la Home (`FEAT-COM-017`).
 *
 * **Es el catálogo, acotado.** No hay una segunda consulta, ni una segunda
 * fórmula, ni un segundo filtro de visibilidad: la misma que sirve la sección
 * «Leer», con los géneros de quien mira y un tope. Esa reutilización es todo
 * el diseño — la regla de qué se puede enseñar tiene cinco puertas y es la
 * más peligrosa del backend, y escrita dos veces una de las dos se queda
 * atrás.
 *
 * Solo obras `IN_CORRECTION`, y no es un filtro de más: el carrusel existe
 * para que alguien empiece a corregir, y una obra que no admite correcciones
 * ocupa el sitio más valioso de la pantalla sin llevar a ninguna parte.
 *
 * **No llama al contrato de ningún otro contexto.** La edad y lo que el
 * lector excluye llegan de quien pregunta, porque un contrato que llama a
 * otro mientras responde convierte las referencias cruzadas en una cadena de
 * llamadas ([`decision:0015`](../../../../../docs/decisions/0015-work-and-reading-ask-each-other.md)).
 */
final readonly class ResolveRecommendedWorks implements RecommendedWorks
{
    /**
     * Un carrusel no es un catálogo: pedir doscientas para enseñar seis es
     * pagar la consulta cara de esa pantalla sin usarla.
     */
    private const MAX = 24;

    public function __construct(
        private CatalogueQuery $catalogue,
        private Clock $clock,
    ) {
    }

    public function forReader(
        string $readerId,
        array $genres,
        bool $readerIsOfAge,
        array $excludedWarnings,
        int $limit,
    ): array {
        $howMany = min(max($limit, 1), self::MAX);

        $page = $this->catalogue->page(new CatalogueCriteria(
            $readerId,
            $readerIsOfAge,
            array_values(array_unique(array_map(strtoupper(...), $genres))),
            self::warnings($excludedWarnings),
            WorkStatus::IN_CORRECTION->value,
            byRelevance: true,
            page: 1,
            perPage: $howMany,
            now: $this->clock->now(),
        ));

        return array_map(self::card(...), $page->entries);
    }

    /**
     * Una etiqueta que no está en el catálogo **se descarta**, no revienta.
     *
     * Al revés que en la sección «Leer», donde la escribe quien filtra y una
     * errata tiene que decirse: aquí llega de los ajustes guardados de una
     * persona, y dejar la Home sin carrusel porque una etiqueta se retiró del
     * catálogo sería romper una pantalla por un dato viejo.
     *
     * @param list<string> $warnings
     *
     * @return list<string>
     */
    private static function warnings(array $warnings): array
    {
        $excluded = [];

        foreach (array_unique(array_map(strtoupper(...), $warnings)) as $code) {
            $warning = ContentWarning::tryFrom($code);

            if (null !== $warning) {
                $excluded[] = $warning->value;
            }
        }

        return $excluded;
    }

    private static function card(CatalogueEntry $entry): RecommendedWork
    {
        return new RecommendedWork(
            $entry->workId,
            $entry->title,
            $entry->synopsis,
            $entry->status,
            $entry->chapterCount,
            // Calculado, no en palabras: la fórmula es de `Work`
            // (`FEAT-WRK-013` `RN-5`), y mandar el dato crudo obligaría a
            // quien pinta la tarjeta a aprendérsela.
            ReadingTime::minutesFor($entry->wordCount),
            $entry->adultsOnly,
            $entry->contentWarnings,
            $entry->genres,
            $entry->credits,
            $entry->correctionsReceived,
        );
    }
}
