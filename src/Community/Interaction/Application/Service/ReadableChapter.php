<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Application\Service;

use LectoresBeta\Community\Interaction\Domain\Exception\ChapterNotReachable;
use LectoresBeta\Reading\BetaReaderAccess\Application\Contract\BetaReaderAccessCheck;
use LectoresBeta\User\Account\Application\Contract\ReaderMaturity;
use LectoresBeta\User\Privacy\Application\Contract\AuthorAudience;
use LectoresBeta\Work\Chapter\Application\Contract\ChapterAccess;
use LectoresBeta\Work\Chapter\Application\Contract\ReadableChapters;

/**
 * Todo lo que tiene que ser cierto antes de tocar un capítulo desde aquí
 * (`FEAT-COM-036`), preguntado una vez y en un sitio.
 *
 * Es el hermano de `EligibleCorrectionBrief` en `Feedback`, y por la misma
 * razón: **el orden de las respuestas es en sí mismo una decisión**, y
 * repartirla por cada handler la convierte en tres decisiones parecidas que
 * un día dejan de serlo.
 *
 * Tres contextos responden, cada uno por su contrato publicado y ninguno por
 * su modelo:
 *
 * - `Work` dice si esta persona puede **leer** el capítulo. La regla de
 *   lectura no se reconstruye aquí: es la más peligrosa del backend y vive
 *   entera al otro lado;
 * - `User` dice si tiene **edad**, porque una obra para adultos no se le
 *   enseña a quien no lo es;
 * - `User` dice además si el autor **acepta comentarios** de esta persona. Es
 *   un techo (`FEAT-USR-038` `RN-2`): el perfil fija el máximo y ninguna obra
 *   lo sube;
 * - `Reading` dice si es **lector beta**, que es lo que hace que
 *   `ON_REQUEST` y `PRIVATE` signifiquen algo.
 *
 * **Leer y escribir se separan a propósito.** Apoyar y comentar exigen el
 * techo del perfil; mirar la cifra de apoyos, no. Quien cerró sus comentarios
 * no ha escondido cuánta gente le ha leído.
 */
final readonly class ReadableChapter
{
    public function __construct(
        private ReadableChapters $chapters,
        private BetaReaderAccessCheck $access,
        private ReaderMaturity $maturity,
        private AuthorAudience $audience,
    ) {
    }

    /**
     * El capítulo, si quien pregunta puede verlo.
     *
     * No existir y no poder verse **responden igual**: un `403` sobre una
     * obra inédita confirmaría que está ahí.
     */
    public function seenBy(string $chapterId, string $readerId): ChapterAccess
    {
        // En qué obra está, primero: el acceso de lector beta se concede
        // **por obra**, así que sin eso no se le puede preguntar a `Reading`.
        $workId = $this->chapters->workOf($chapterId);

        if (null === $workId) {
            throw ChapterNotReachable::create();
        }

        $access = $this->chapters->of(
            $chapterId,
            $readerId,
            $this->maturity->isOfAge($readerId),
            $this->access->hasAccessTo($workId, $readerId),
        );

        if (null === $access || !$access->readable) {
            throw ChapterNotReachable::create();
        }

        return $access;
    }

    /**
     * Lo mismo, y además que el autor admita comentarios de esta persona.
     *
     * El techo se comprueba **después** de la visibilidad, no antes: quien no
     * puede ver el capítulo no debe distinguir «no existe» de «el autor no te
     * admite», porque lo segundo ya confirma que existe.
     */
    public function writtenOnBy(string $chapterId, string $readerId): ChapterAccess
    {
        $access = $this->seenBy($chapterId, $readerId);

        if (!$this->audience->acceptsCommentsFrom($access->authorId, $readerId)) {
            throw ChapterNotReachable::becauseTheAuthorTookCommentsDown();
        }

        return $access;
    }
}
