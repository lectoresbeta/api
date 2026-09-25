<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Recommendation\Domain\Repository;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Ranking\Domain\Entity\AuthorStats;

/**
 * De dónde salen los autores que se sugieren (`FEAT-COM-016`).
 *
 * **Todo sale de proyecciones**, no de contar en cada carga: los contadores
 * de seguidores y publicaciones crecen, y un `COUNT` por tarjeta en cada
 * onboarding no escala (`RN-6` de la ficha, y el criterio que lo prueba).
 */
interface AuthorSuggestionRepository
{
    /**
     * Autores que escriben en alguno de estos géneros, de más obras a menos
     * y, a igualdad, de más seguidores a menos.
     *
     * @param list<string> $genreCodes
     * @param list<string> $excluded   quien pregunta y a quien ya sigue
     *
     * @return list<AuthorStats>
     */
    public function byGenres(array $genreCodes, array $excluded, int $limit): array;

    /**
     * Los más seguidos de la plataforma, sin filtrar por género.
     *
     * Es el segundo tramo de la cadena de relleno, y es deliberado: **es
     * preferible proponer autores populares aunque no encajen** que enseñar
     * una pantalla casi vacía.
     *
     * @param list<string> $excluded
     *
     * @return list<AuthorStats>
     */
    public function mostFollowed(array $excluded, int $limit): array;

    /**
     * En qué géneros de los pedidos escribe cada uno de estos autores, para
     * poder decir **por qué** se le sugiere.
     *
     * @param list<string> $authorIds
     * @param list<string> $genreCodes
     *
     * @return array<string, list<string>>
     */
    public function matchedGenres(array $authorIds, array $genreCodes): array;

    /**
     * Los géneros que le interesan a esta persona, de la copia local.
     *
     * @return list<string>
     */
    public function genresOf(MemberId $memberId): array;

    /**
     * Cuántos autores hay en total que podrían sugerirse, descontando a
     * quien pregunta y a quien ya sigue.
     *
     * Existe para distinguir dos silencios que significan cosas distintas:
     * «no hay gente» y «ya los sigues a todos».
     *
     * @param list<string> $excluded
     */
    public function countCandidates(array $excluded): int;
}
