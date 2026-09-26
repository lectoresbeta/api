<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\ContentReview\Infrastructure\Review;

use LectoresBeta\Moderation\ContentReview\Application\Port\ContentReviewer;
use LectoresBeta\Moderation\ContentReview\Domain\ValueObject\ReviewVerdict;

/**
 * El revisor de hoy: **aprueba todo, sin condiciones** (`FEAT-MOD-011`
 * `RN-2`).
 *
 * No consulta nada externo, y eso importa más de lo que parece: `MOD-37`
 * —si mandar obra inédita a un servicio de terceros es aceptable— es una
 * decisión de producto y probablemente de contrato, y esta implementación la
 * deja sin tomar. El día que haya una que lea de verdad, esa pregunta habrá
 * que contestarla antes.
 *
 * La versión es literal y se guarda con cada veredicto. Cuando llegue la
 * siguiente, los veredictos viejos seguirán diciendo con qué reglas se
 * tomaron.
 */
final readonly class AlwaysApprovesContentReviewer implements ContentReviewer
{
    public const MECHANISM = 'NULL';

    public const VERSION = '1';

    public function review(string $text): ReviewVerdict
    {
        return ReviewVerdict::passed(self::MECHANISM, self::VERSION);
    }
}
