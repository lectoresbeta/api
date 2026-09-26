<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Questionnaire\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * Somebody else saved first (`FEAT-WRK-014`).
 *
 * Two tabs editing the same questionnaire is not a rare accident, and the
 * loser would not notice: the second save would quietly overwrite questions
 * the first one added. Since this configuration sets the price of every
 * correction, silently losing half of it is not acceptable.
 */
final class StaleQuestionnaireVersion extends \DomainException implements BusinessFailure
{
    public static function expected(int $current): self
    {
        return new self(\sprintf('The questionnaire has changed since you loaded it. Its current version is %d.', $current));
    }

    public function errorCode(): string
    {
        return 'STALE_VERSION';
    }

    public function kind(): FailureKind
    {
        return FailureKind::CONFLICT;
    }
}
