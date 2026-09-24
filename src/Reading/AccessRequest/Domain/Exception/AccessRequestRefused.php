<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessRequest\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * The request cannot go ahead, and this is why (`FEAT-RDG-002`,
 * `FEAT-RDG-003`).
 *
 * One class for seven refusals because they are one thing —«this cannot
 * proceed»— and nobody catches them one by one: they all end up as an RFC
 * 9457 problem with a code. Seven classes would be seven files of ceremony
 * for a `match` that already lives in `ProblemFactory`.
 *
 * The kind travels per factory, which is what lets the same idea answer
 * `422`, `409` and `410`. They are genuinely different situations for the
 * caller: `422` means «you asked for something that makes no sense», `409`
 * «the state is not the one you assumed», `410` «it was here and it is gone».
 */
final class AccessRequestRefused extends \DomainException implements BusinessFailure
{
    private function __construct(
        private readonly string $failureCode,
        private readonly FailureKind $failureKind,
        string $message,
    ) {
        parent::__construct($message);
    }

    /**
     * `PUBLIC` and `PRIVATE` both land here, for opposite reasons: in one
     * there is nothing to ask for, in the other asking is not the way in.
     */
    public static function workDoesNotTakeRequests(): self
    {
        return new self(
            'WORK_DOES_NOT_TAKE_REQUESTS',
            FailureKind::INVALID,
            'Only a work open on request takes access requests.',
        );
    }

    /**
     * Not a `404`, and that is deliberate: the author is looking at their own
     * work. Pretending it does not exist would confuse them about something
     * of theirs.
     */
    public static function authorCannotAskForAccess(): self
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

    public static function alreadyPending(): self
    {
        return new self(
            'REQUEST_ALREADY_PENDING',
            FailureKind::CONFLICT,
            'There is already an open request for this work.',
        );
    }

    public static function alreadyResolved(): self
    {
        return new self(
            'REQUEST_ALREADY_RESOLVED',
            FailureKind::CONFLICT,
            'That request was already accepted, rejected or withdrawn.',
        );
    }

    /**
     * `410` and not `404`: whoever resolves it knows the work existed, and
     * `WorkDeleted` simply has not been delivered yet.
     */
    public static function workIsGone(): self
    {
        return new self(
            'WORK_GONE',
            FailureKind::GONE,
            'That work no longer exists.',
        );
    }

    /**
     * Never read as a rejection. Guessing in an operation that grants access
     * to unpublished writing is exactly where guessing must not happen.
     */
    public static function unknownDecision(): self
    {
        return new self(
            'UNKNOWN_DECISION',
            FailureKind::INVALID,
            'A request is either ACCEPTED or REJECTED.',
        );
    }

    /**
     * Un estado que no existe en el filtro de la bandeja. Se rechaza en vez
     * de ignorarse por lo mismo que en el catálogo: servir otra lista deja a
     * quien pregunta sin forma de notarlo.
     */
    public static function unknownStatusFilter(): self
    {
        return new self(
            'UNKNOWN_FILTER_VALUE',
            FailureKind::INVALID,
            'A request is PENDING, ACCEPTED, REJECTED or CANCELLED; ALL asks for every one.',
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
