<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * The chapter does not exist, is hidden, is blocked, or belongs to a work the
 * reader may not open (`FEAT-WRK-004` `RN-2`).
 *
 * All of it is one answer. A chapter that answered differently depending on
 * *why* it is out of reach would leak the shape of somebody's unpublished
 * novel.
 */
final class ChapterNotFound extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('That chapter does not exist.');
    }

    public function errorCode(): string
    {
        return 'CHAPTER_NOT_FOUND';
    }

    public function kind(): FailureKind
    {
        return FailureKind::NOT_FOUND;
    }
}
