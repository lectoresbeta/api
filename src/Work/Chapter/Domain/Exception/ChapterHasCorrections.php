<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * Un capítulo que alguien ha corregido no se borra (`FEAT-WRK-003` `RN-6`).
 *
 * Borrarlo destruiría el trabajo de quien lo corrigió y el rastro de un
 * cobro. Se oculta, que es lo mismo de cara al lector y no destruye nada.
 */
final class ChapterHasCorrections extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('That chapter has corrections and cannot be removed; hide it instead.');
    }

    public function errorCode(): string
    {
        return 'CHAPTER_HAS_CORRECTIONS';
    }

    public function kind(): FailureKind
    {
        return FailureKind::CONFLICT;
    }
}
