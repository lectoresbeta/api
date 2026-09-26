<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\Service;

use LectoresBeta\Work\Manuscript\Domain\Enum\ContentWarning;
use LectoresBeta\Work\Manuscript\Domain\Exception\InvalidContentRating;

/**
 * Qué es una clasificación de contenido válida (`FEAT-WRK-017`).
 *
 * Dos reglas, y las dos existen por la misma razón: la declaración del autor
 * es lo que después le protege de una reclamación (`RN-6`) y lo que le hace
 * responsable si engaña (`RN-7`). Una declaración que el sistema interpreta
 * por su cuenta no sirve para ninguna de las dos cosas.
 *
 * - **El catálogo es cerrado** (`RN-4`). Una etiqueta que no se puede filtrar
 *   no es una etiqueta, y texto libre no se filtra.
 * - **El público se declara siempre.** Omitir el indicador no significa «apta
 *   para menores»: significa que nadie lo ha dicho, y dar por supuesto lo
 *   más permisivo es exactamente el error que esta funcionalidad existe para
 *   evitar.
 *
 * Declarar de más no se comprueba: etiquetar una obra con las cinco no hace
 * daño a nadie, solo le cuesta lectores a su autor. Es lo contrario del tope
 * de temáticas, que existe porque una obra con ocho aparecería en cualquier
 * búsqueda; aquí las etiquetas **quitan** obras del catálogo, así que pasarse
 * se castiga solo.
 */
final class ContentRatingPolicy
{
    /**
     * @param list<string> $codes
     *
     * @return list<ContentWarning>
     */
    public function warnings(array $codes): array
    {
        $declared = [];
        $unknown = [];

        foreach (array_unique(array_map(strtoupper(...), $codes)) as $code) {
            $warning = ContentWarning::tryFrom($code);

            if (null === $warning) {
                $unknown[] = $code;

                continue;
            }

            $declared[$warning->value] = $warning;
        }

        if ([] !== $unknown) {
            throw InvalidContentRating::unknownWarnings($unknown);
        }

        return array_values($declared);
    }

    public function audience(?bool $adultsOnly): bool
    {
        if (null === $adultsOnly) {
            throw InvalidContentRating::undeclaredAudience();
        }

        return $adultsOnly;
    }
}
