<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * The chapter is not taking corrections right now (`FEAT-FBK-003`).
 *
 * A conflict and not a refusal: nothing is wrong with who is asking, and the
 * same request may well succeed tomorrow. The distinction matters to the
 * interface, which has something useful to say in each case.
 */
final class ChapterNotCorrectable extends \DomainException implements BusinessFailure
{
    private function __construct(private readonly string $failureCode, string $message)
    {
        parent::__construct($message);
    }

    public static function becauseTheWorkIsClosed(): self
    {
        return new self(
            'WORK_NOT_IN_CORRECTION',
            'This work is not open for correction.',
        );
    }

    /**
     * There is nothing to answer, so there would be nothing to pay for
     * either: the writing term of the price would be zero.
     */
    public static function becauseThereIsNoQuestionnaire(): self
    {
        return new self(
            'WORK_WITHOUT_QUESTIONNAIRE',
            'The author has not written a questionnaire for this work yet.',
        );
    }

    /**
     * The author cannot pay for it, or the chapter already has as many
     * corrections under way as it admits
     * ([`FEAT-CRD-009`](../../../../../docs/features/credits/FEAT-CRD-009-balance-check-on-correction-start.md)).
     *
     * **Why the reason is not spelled out:** the two cases would tell a
     * stranger how the author's balance is doing, which is nobody else's
     * business. The interface says «ahora mismo no» and offers the rest of
     * the catalogue.
     */
    public static function becauseItIsNotTakingCorrectionsNow(): self
    {
        return new self(
            'CHAPTER_NOT_TAKING_CORRECTIONS',
            'This chapter is not taking new corrections right now.',
        );
    }

    public static function becauseItWasAlreadyCorrected(): self
    {
        return new self(
            'CORRECTION_ALREADY_SUBMITTED',
            'You have already sent a correction of this chapter.',
        );
    }

    public function errorCode(): string
    {
        return $this->failureCode;
    }

    public function kind(): FailureKind
    {
        return FailureKind::CONFLICT;
    }
}
