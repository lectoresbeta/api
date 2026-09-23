<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * This person may not correct this chapter (`FEAT-FBK-003`).
 *
 * The messages say what rule stopped it and never anything about the work:
 * whoever is being refused may be somebody the author never let in, and
 * confirming that a chapter exists is already telling them something.
 */
final class CorrectionNotAllowed extends \DomainException implements BusinessFailure
{
    private function __construct(private readonly string $failureCode, string $message)
    {
        parent::__construct($message);
    }

    public static function toTheAuthor(): self
    {
        return new self(
            'AUTHOR_CANNOT_CORRECT',
            'An author cannot correct their own work.',
        );
    }

    public static function withoutBetaReaderAccess(): self
    {
        return new self(
            'NOT_A_BETA_READER',
            'This work only accepts corrections from its beta readers.',
        );
    }

    public function errorCode(): string
    {
        return $this->failureCode;
    }

    public function kind(): FailureKind
    {
        return FailureKind::FORBIDDEN;
    }
}
