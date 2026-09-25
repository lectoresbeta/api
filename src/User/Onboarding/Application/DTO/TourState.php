<?php

declare(strict_types=1);

namespace LectoresBeta\User\Onboarding\Application\DTO;

/**
 * Si hay que enseñar un tour, y por dónde iba (`FEAT-USR-026`).
 *
 * `pending` es lo único que el cliente necesita para decidir. `lastStep` está
 * para la métrica —en qué paso abandona la gente (`RN-4`)— y para que quien
 * cerró la pestaña a mitad vuelva por donde iba.
 */
final readonly class TourState
{
    public function __construct(
        public string $tourId,
        public bool $pending,
        public ?int $lastStep,
    ) {
    }
}
