<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Ingest\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * Esa subida no está (`FEAT-WRK-002`).
 *
 * No existir, ser de otra persona, haber caducado y estar ya confirmada
 * responden **lo mismo**: que exista es información sobre lo que alguien
 * está escribiendo.
 */
final class UploadNotFound extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('That upload is not there. It may have expired.');
    }

    public function errorCode(): string
    {
        return 'MANUSCRIPT_UPLOAD_NOT_FOUND';
    }

    public function kind(): FailureKind
    {
        return FailureKind::NOT_FOUND;
    }
}
