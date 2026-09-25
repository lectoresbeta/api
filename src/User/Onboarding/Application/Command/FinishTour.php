<?php

declare(strict_types=1);

namespace LectoresBeta\User\Onboarding\Application\Command;

/**
 * Dar un tour por visto (`FEAT-USR-026`).
 *
 * `dismissed` distingue **cerrarlo** de **terminarlo**, y los dos cuentan
 * como visto: la diferencia no cambia lo que se enseña, cambia lo que se
 * puede aprender de ello. Sin esa distinción, «lo vieron entero» y «lo
 * cerraron en el primer globo» serían el mismo dato.
 */
final readonly class FinishTour
{
    public function __construct(
        public string $userId,
        public ?string $tourId,
        public ?int $lastStep,
        public bool $dismissed,
    ) {
    }
}
