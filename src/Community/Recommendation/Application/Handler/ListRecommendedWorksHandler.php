<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Recommendation\Application\Handler;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Recommendation\Application\DTO\RecommendedWorks;
use LectoresBeta\Community\Recommendation\Application\Query\ListRecommendedWorks;
use LectoresBeta\Community\Recommendation\Domain\Repository\AuthorSuggestionRepository;
use LectoresBeta\User\Account\Application\Contract\ReaderMaturity;
use LectoresBeta\User\Preferences\Application\Contract\ReaderContentPreferences;
use LectoresBeta\Work\Catalogue\Application\Contract\RecommendedWorks as Catalogue;

/**
 * Lo primero que se ve al entrar (`FEAT-COM-017`).
 *
 * **El reparto de responsabilidades es toda la ficha.** `Community` sabe qué
 * le gusta a esta persona —lo proyecta desde `User` y es suyo—; qué obras
 * merece la pena poner delante, y en qué orden, lo sabe el catálogo de
 * `Work`, que ya resuelve las cinco puertas de visibilidad, la clasificación
 * por edad y la fórmula de reparto de `decision:0008`.
 *
 * De ahí que aquí no haya proyección de obras. La ficha original pedía una
 * (`RN-7`), y hacerla habría significado reescribir en este contexto la regla
 * más peligrosa del backend para poder ordenar por una fórmula que también
 * habría que copiar. Lo que se proyecta es lo que de verdad es de aquí: los
 * géneros.
 *
 * La edad y lo que el lector ha excluido se preguntan **aquí** y se pasan al
 * contrato. No es comodidad: si los preguntara el contrato de `Work`, estaría
 * llamando al de `User` mientras responde, que es lo que `decision:0015`
 * prohíbe.
 */
final readonly class ListRecommendedWorksHandler
{
    public function __construct(
        private AuthorSuggestionRepository $genres,
        private Catalogue $catalogue,
        private ReaderMaturity $maturity,
        private ReaderContentPreferences $preferences,
        private int $minimum,
        private int $maximum,
    ) {
    }

    public function __invoke(ListRecommendedWorks $query): RecommendedWorks
    {
        $member = MemberId::fromString($query->memberId);
        $howMany = $query->howMany ?? $this->maximum;

        $ofAge = $this->maturity->isOfAge($query->memberId);
        $excluded = $this->preferences->excludedBy($query->memberId);
        $chosen = $this->genres->genresOf($member);

        $found = [] === $chosen
            ? []
            : $this->catalogue->forReader($query->memberId, $chosen, $ofAge, $excluded, $howMany);

        // Segundo tramo de la cadena de relleno: cualquier género. Es
        // preferible enseñar algo que no encaje del todo a dejar la Home
        // empezando por el muro, que es donde no hay nada que hacer si
        // todavía no sigues a nadie.
        if (\count($found) < $this->minimum) {
            $found = $this->catalogue->forReader($query->memberId, [], $ofAge, $excluded, $howMany);
        }

        // Un solo motivo, y no dos. Estuvo la tentación de distinguir «no
        // eligió géneros» de «no hay nada», pero al llegar aquí ya se ha
        // mirado **el catálogo entero**: decir que el problema son sus
        // géneros sería culpar al usuario de una plataforma vacía.
        if ([] === $found) {
            return new RecommendedWorks(false, 'NOTHING_TO_CORRECT_YET', []);
        }

        return new RecommendedWorks(true, null, $found);
    }
}
