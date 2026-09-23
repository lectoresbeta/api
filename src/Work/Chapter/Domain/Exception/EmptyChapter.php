<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * A chapter with no words (`FEAT-WRK-001` `RN-2`).
 *
 * Checked **after** sanitising, not before: content that is nothing but
 * disallowed markup — a paste that was all `div`s and images — comes out
 * empty, and the author should be told that rather than left with a chapter
 * that silently holds nothing.
 */
final class EmptyChapter extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('The chapter has no text.');
    }

    public function errorCode(): string
    {
        return 'EMPTY_CHAPTER';
    }

    public function kind(): FailureKind
    {
        return FailureKind::INVALID;
    }
}
