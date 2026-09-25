<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * No hay ninguna corrección que esta persona pueda leer.
 *
 * **Una corrección ajena responde exactamente lo mismo que una inexistente**
 * (`FEAT-FBK-004` `RN-1`). No es cortesía: saber que existe una corrección
 * sobre un capítulo concreto es saber algo de una obra inédita que no es
 * suya, y de quién la está leyendo.
 *
 * Un borrador ajeno también responde así, y además es cierto: para quien no
 * lo escribe, un borrador no existe.
 */
final class CorrectionNotFound extends \DomainException implements BusinessFailure
{
    public static function withId(string $correctionId): self
    {
        // El identificador va al mensaje interno, nunca a la respuesta: el
        // `detail` que llega al cliente lo escribe `ProblemFactory`.
        return new self(\sprintf('There is no readable correction with id %s.', $correctionId));
    }

    public function errorCode(): string
    {
        return 'CORRECTION_NOT_FOUND';
    }

    public function kind(): FailureKind
    {
        return FailureKind::NOT_FOUND;
    }
}
