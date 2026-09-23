<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * The answers do not satisfy what the author asked (`FEAT-FBK-003` `RN-4`,
 * `RN-5`).
 *
 * The minimum length is not formality: it is the **first defence against
 * being paid for «ok, muy bueno»**. It is not enough on its own — a long
 * answer can be just as empty — and the real control is still to be built
 * ([`FEAT-FBK-012`](../../../../../docs/features/feedback/FEAT-FBK-012-correction-fraud-control.md)).
 *
 * Every message names **which question** failed, because a form that says
 * «something is wrong» sends the reader hunting through twenty fields.
 */
final class IncompleteCorrection extends \DomainException implements BusinessFailure
{
    private function __construct(private readonly string $failureCode, string $message)
    {
        parent::__construct($message);
    }

    public static function missingAnswerTo(int $position): self
    {
        return new self(
            'MISSING_REQUIRED_ANSWER',
            \sprintf('Question %d must be answered.', $position),
        );
    }

    public static function answerTooShort(int $position, int $words, int $minimum): self
    {
        return new self(
            'ANSWER_TOO_SHORT',
            \sprintf('The answer to question %d has %d words; the author asks for at least %d.', $position, $words, $minimum),
        );
    }

    public static function answerTooLong(int $position, int $words, int $maximum): self
    {
        return new self(
            'ANSWER_TOO_LONG',
            \sprintf('The answer to question %d has %d words; the author allows at most %d.', $position, $words, $maximum),
        );
    }

    /**
     * An answer to a question that is not in the version being answered. It
     * is not an attack, usually: a panel left open while the author rewrote
     * the questionnaire.
     */
    public static function answeringAnUnknownQuestion(): self
    {
        return new self(
            'UNKNOWN_QUESTION',
            'One of the answers does not belong to the questionnaire being answered.',
        );
    }

    public function errorCode(): string
    {
        return $this->failureCode;
    }

    public function kind(): FailureKind
    {
        return FailureKind::INVALID;
    }
}
