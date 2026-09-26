<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * El orden que llega no contiene exactamente los capítulos de la obra (`FEAT-WRK-003` `RN-2`).
 *
 * Se rechaza **entero** y no a medias a propósito: aplicar la mitad de un
 * reordenamiento deja la obra en un estado que el autor no pidió y no sabe
 * leer.
 */
final class ChapterOrderIncomplete extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('The order must list exactly the chapters of this work.');
    }

    public function errorCode(): string
    {
        return 'CHAPTER_ORDER_INCOMPLETE';
    }

    public function kind(): FailureKind
    {
        return FailureKind::INVALID;
    }
}
