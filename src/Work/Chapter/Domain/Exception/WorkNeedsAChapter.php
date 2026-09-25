<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * Una obra publicada no puede quedarse sin capítulos (`FEAT-WRK-003` `RN-8`).
 *
 * En borrador sí: ahí no hay nadie leyendo y vaciarla es parte de escribir.
 */
final class WorkNeedsAChapter extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('A published work cannot be left without chapters.');
    }

    public function errorCode(): string
    {
        return 'WORK_NEEDS_A_CHAPTER';
    }

    public function kind(): FailureKind
    {
        return FailureKind::CONFLICT;
    }
}
