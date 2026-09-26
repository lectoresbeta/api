<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Recommendation\Application\DTO;

use LectoresBeta\Work\Catalogue\Application\Contract\RecommendedWork;

/**
 * El carrusel de la Home (`FEAT-COM-017`).
 *
 * **La decisión de enseñar la sección la toma el servidor**, igual que en
 * `FEAT-COM-016`, y de ahí `shouldDisplay`. Si la tomara el cliente —«si
 * vienen menos de tres, no la pinto»— habría dos sitios donde cambiar el
 * umbral y uno se quedaría atrás.
 *
 * Una lista vacía no es un error: en una plataforma recién lanzada no hay
 * obras que recomendar, y ese es justo el momento en que todo el mundo pasa
 * por la Home.
 */
final readonly class RecommendedWorks
{
    /**
     * @param list<RecommendedWork>         $works
     * @param 'NOTHING_TO_CORRECT_YET'|null $reason por qué no hay carrusel,
     *                                              cuando no lo hay
     */
    public function __construct(
        public bool $shouldDisplay,
        public ?string $reason,
        public array $works,
    ) {
    }
}
