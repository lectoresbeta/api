<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\ContentReview\Domain\ValueObject;

use LectoresBeta\Moderation\ContentReview\Domain\Enum\ContentReviewOutcome;

/**
 * Lo que el revisor automático responde sobre un texto (`FEAT-MOD-011`).
 *
 * **Aprobado o marcado, con motivo. Nada más.** Ni puntuaciones, ni
 * categorías, ni probabilidades: cuanto más rico sea el contrato, más difícil
 * será sustituir la implementación, y el día que llegue una de verdad habrá
 * que poder cambiarla sin tocar nada de esto.
 *
 * La versión viaja con el veredicto y no se pide aparte (`RN-3`): cuando haya
 * varias generaciones de revisor, saber **qué revisó qué** es lo único que
 * permite releer una decisión vieja con las reglas con que se tomó.
 */
final readonly class ReviewVerdict
{
    private function __construct(
        public ContentReviewOutcome $outcome,
        public string $mechanism,
        public string $version,
        public ?string $reason,
    ) {
    }

    public static function passed(string $mechanism, string $version): self
    {
        return new self(ContentReviewOutcome::PASSED, $mechanism, $version, null);
    }

    public static function flagged(string $mechanism, string $version, string $reason): self
    {
        return new self(ContentReviewOutcome::FLAGGED, $mechanism, $version, $reason);
    }

    public function isFlagged(): bool
    {
        return ContentReviewOutcome::FLAGGED === $this->outcome;
    }
}
