<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Domain\Exception;

/**
 * A cursor that this application did not produce.
 *
 * It is refused rather than ignored, for the same reason an unknown filter
 * value is: serving the first page instead would answer a different question
 * from the one asked, and the caller has no way of noticing.
 */
final class InvalidCursor extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('That cursor is not one this API issued.');
    }

    public function errorCode(): string
    {
        return 'INVALID_CURSOR';
    }

    public function kind(): FailureKind
    {
        return FailureKind::INVALID;
    }
}
