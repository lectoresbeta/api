<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderAccess\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * La obra no está, o no debe revelarse que está (`FEAT-RDG-010`).
 *
 * Una obra ajena responde lo mismo que una que no existe. Confirmar que está
 * ahí ya sería decir algo de una obra que su autor no ha publicado.
 *
 * **No hay un «ese acceso no existe»**, y es deliberado: revocar a quien no
 * tiene acceso no es un error (`RN-2`), así que nunca hace falta contarlo.
 */
final class BetaReaderAccessNotFound extends \DomainException implements BusinessFailure
{
    public static function work(): self
    {
        return new self('That work does not exist.');
    }

    public function errorCode(): string
    {
        return 'WORK_NOT_FOUND';
    }

    public function kind(): FailureKind
    {
        return FailureKind::NOT_FOUND;
    }
}
