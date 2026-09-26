<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\Service;

use LectoresBeta\User\Profile\Application\Contract\GenreCatalogue;
use LectoresBeta\Work\Manuscript\Domain\Exception\InvalidWorkGenres;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkGenreRepository;
use LectoresBeta\Work\Manuscript\Domain\Service\GenrePolicy;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * Clasificar una obra (`FEAT-WRK-001`, y lo que desbloquea `FEAT-WRK-012`
 * `RN-5`).
 *
 * Lo comparten crear y reclasificar porque son la misma operación con
 * distinta puerta delante: **dejar la lista como el autor la ve**. No hay
 * «añadir una temática», y no es una omisión — clasificar es decir qué es una
 * obra, no ir apilando etiquetas.
 *
 * Las temáticas viven en `User`, que las curó para el onboarding, y se
 * validan contra su contrato publicado. Que la lista esté en un solo sitio es
 * lo que hace que «Ficción» signifique lo mismo en las dos pantallas; si cada
 * contexto tuviera la suya, el día que alguien renombrase una, las obras y
 * las personas dejarían de encontrarse.
 */
final readonly class DeclareGenres
{
    public function __construct(
        private WorkGenreRepository $genres,
        private GenreCatalogue $catalogue,
    ) {
    }

    /**
     * @param list<string> $codes
     */
    public function on(WorkId $workId, array $codes): void
    {
        $this->genres->replaceAll($workId, $this->validated($codes));
    }

    /**
     * @param list<string> $codes
     *
     * @return list<string>
     */
    private function validated(array $codes): array
    {
        $declared = array_values(array_unique(array_map(strtoupper(...), $codes)));

        if (\count($declared) > GenrePolicy::MAX_GENRES) {
            throw InvalidWorkGenres::tooMany(\count($declared));
        }

        $unknown = $this->catalogue->unknownAmong($declared);

        if ([] !== $unknown) {
            // Se nombran: quien clasifica su obra necesita saber cuál de los
            // tres que envió no existe.
            throw InvalidWorkGenres::unknown($unknown);
        }

        return $declared;
    }
}
