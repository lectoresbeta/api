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

    /**
     * The author's global privacy setting is a **ceiling** over each work's
     * access mode (`FEAT-USR-038` `RN-2`): the profile sets the maximum and a
     * work may lower it, never raise it. Without this, hardening the setting
     * would close a door that stays open on every `PUBLIC` work.
     *
     * It says nothing about who the author let in, only that they are not
     * taking comments: even a beta reader with live access is refused while
     * the ceiling is down.
     */
    public static function becauseTheAuthorTookCommentsDown(): self
    {
        return new self(
            'AUTHOR_DOES_NOT_ACCEPT_COMMENTS',
            'This author is not accepting comments on their texts right now.',
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
