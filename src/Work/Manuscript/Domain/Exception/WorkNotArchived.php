<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * Se pide restaurar una obra que no está archivada.
 *
 * Se distingue del éxito silencioso a propósito: quien llama cree que está
 * recuperando algo, y merece saber que no había nada que recuperar.
 */
final class WorkNotArchived extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('That work is not archived.');
    }

    public function errorCode(): string
    {
        return 'WORK_NOT_ARCHIVED';
    }

    public function kind(): FailureKind
    {
        return FailureKind::CONFLICT;
    }
}
