<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Subscription\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * Las listas de alguien a quien no se puede ver (`FEAT-COM-027` `RN-2`).
 *
 * Responde **lo mismo que su perfil**, y con el mismo código: `404`
 * `PROFILE_NOT_FOUND`. Es deliberado hasta en el detalle de compartir código
 * con `User` sin compartir clase — quien pregunta no debe poder notar por qué
 * camino entró, y un código distinto sería una forma de contar que la cuenta
 * existe.
 */
final class ProfileListNotVisible extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('That profile does not exist.');
    }

    public function errorCode(): string
    {
        return 'PROFILE_NOT_FOUND';
    }

    public function kind(): FailureKind
    {
        return FailureKind::NOT_FOUND;
    }
}
