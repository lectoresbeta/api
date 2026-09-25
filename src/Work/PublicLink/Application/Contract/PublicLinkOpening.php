<?php

declare(strict_types=1);

namespace LectoresBeta\Work\PublicLink\Application\Contract;

/**
 * Qué abre un token, para un capítulo concreto (`FEAT-WRK-010`).
 *
 * Responde de una vez todo lo que hace falta antes de aceptar una corrección
 * anónima: **qué obra es**, **si el enlace sigue sirviendo**, **cuántas
 * correcciones admite** y **cuánto habría valido esta**. Cuatro preguntas
 * sueltas serían cuatro instantes distintos, y entre el primero y el cuarto
 * el autor puede haber revocado.
 *
 * `wouldBeWorth` es una cifra **informativa**: es lo que alimenta el mensaje
 * de captación de
 * [`FEAT-FBK-008`](../../../../../docs/features/feedback/FEAT-FBK-008-public-link-correction.md).
 * Sale de la señal que `Work` ya mantiene para la insignia del catálogo
 * (`FEAT-CRD-013`), así que este contexto no calcula precios ni empieza a
 * hacerlo ahora: repite el último que `Credits` anunció.
 */
final readonly class PublicLinkOpening
{
    public function __construct(
        public string $publicLinkId,
        public string $workId,
        public string $authorId,
        public bool $usable,
        public int $maxCorrections,
        /** Si el capítulo preguntado es de esa obra y se puede servir. */
        public bool $chapterIsServed,
        public ?int $wouldBeWorth,
    ) {
    }
}
