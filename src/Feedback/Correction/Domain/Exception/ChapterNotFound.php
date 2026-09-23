<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * No chapter this context can collect corrections for.
 *
 * It is also the answer when the chapter exists but is blocked by a
 * moderation claim: not there and not yours are the same answer, because
 * telling them apart tells a stranger something about somebody else's work.
 */
final class ChapterNotFound extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('There is no such chapter.');
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
