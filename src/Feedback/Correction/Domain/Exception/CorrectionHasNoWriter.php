<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * La corrección llegó por enlace público, así que **no hay nadie a quien
 * contestar** (`FEAT-FBK-005` `RN-5`).
 *
 * Quien la escribió no tiene cuenta: no hay forma de avisarle ni sitio donde
 * pueda leer la respuesta. Aceptarla y no entregarla sería peor que
 * rechazarla, porque el autor creería haber contestado.
 *
 * Valorar sí se puede: eso le sirve al autor para ordenar lo suyo, y no
 * necesita destinatario.
 */
final class CorrectionHasNoWriter extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('That correction came through a public link: there is nobody to reply to.');
    }

    public function errorCode(): string
    {
        return 'CORRECTION_HAS_NO_AUTHOR';
    }

    public function kind(): FailureKind
    {
        return FailureKind::CONFLICT;
    }
}
