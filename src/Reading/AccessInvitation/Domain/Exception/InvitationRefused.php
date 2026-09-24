<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessInvitation\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * The invitation cannot go ahead, and this is why (`FEAT-RDG-004`,
 * `FEAT-RDG-005`).
 *
 * The mirror of `AccessRequestRefused`, and the same shape for the same
 * reason: nobody catches these one by one, they all end up as an RFC 9457
 * problem with a code, and the kind travels per factory so that one idea can
 * answer `422`, `409` and `410`.
 */
final class InvitationRefused extends \DomainException implements BusinessFailure
{
    private function __construct(
        private readonly string $failureCode,
        private readonly FailureKind $failureKind,
        string $message,
    ) {
        parent::__construct($message);
    }

    /**
     * Not a `404`: the author picked this person, and telling them the work
     * does not exist would be answering about the wrong thing.
     */
    public static function userNotFound(): self
    {
        return new self(
            'USER_NOT_FOUND',
            FailureKind::INVALID,
            'There is no such user to invite.',
        );
    }

    public static function authorCannotBeInvited(): self
    {
        return new self(
            'AUTHOR_CANNOT_BE_BETA_READER',
            FailureKind::INVALID,
            'The author of a work is not a beta reader of it.',
        );
    }

    public static function alreadyABetaReader(): self
    {
        return new self(
            'ALREADY_A_BETA_READER',
            FailureKind::CONFLICT,
            'That reader already has access to this work.',
        );
    }

    /**
     * They asked first. What the author has to do is accept that request, not
     * create a second object meaning the same thing.
     */
    public static function requestAlreadyPending(): self
    {
        return new self(
            'REQUEST_ALREADY_PENDING',
            FailureKind::CONFLICT,
            'That person already asked for access; resolve their request instead.',
        );
    }

    public static function alreadyPending(): self
    {
        return new self(
            'INVITATION_ALREADY_PENDING',
            FailureKind::CONFLICT,
            'That person has already been invited to this work.',
        );
    }

    public static function alreadyResolved(): self
    {
        return new self(
            'INVITATION_ALREADY_RESOLVED',
            FailureKind::CONFLICT,
            'That invitation was already accepted, declined or withdrawn.',
        );
    }

    public static function workIsGone(): self
    {
        return new self(
            'WORK_GONE',
            FailureKind::GONE,
            'That work no longer exists.',
        );
    }

    public static function unknownDecision(): self
    {
        return new self(
            'UNKNOWN_DECISION',
            FailureKind::INVALID,
            'An invitation is either ACCEPTED or DECLINED.',
        );
    }

    public static function unknownStatusFilter(): self
    {
        return new self(
            'UNKNOWN_FILTER_VALUE',
            FailureKind::INVALID,
            'An invitation is PENDING, ACCEPTED, DECLINED or CANCELLED; ALL asks for every one.',
        );
    }

    public function errorCode(): string
    {
        return $this->failureCode;
    }

    public function kind(): FailureKind
    {
        return $this->failureKind;
    }
}
