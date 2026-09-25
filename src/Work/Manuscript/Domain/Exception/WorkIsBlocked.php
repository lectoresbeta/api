<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * La obra está bloqueada por una reclamación estimada (`FEAT-WRK-006`
 * `RN-8`).
 *
 * No se archiva: sería la vía para hacer desaparecer contenido reclamado, y
 * el bloqueo existe justamente para conservarlo mientras alguien pueda
 * discutir la decisión.
 */
final class WorkIsBlocked extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('That work is blocked by moderation.');
    }

    public function errorCode(): string
    {
        return 'WORK_BLOCKED';
    }

    public function kind(): FailureKind
    {
        return FailureKind::CONFLICT;
    }
}
