<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * A genre that is not in the catalogue (`FEAT-USR-023` `RN-3`,
 * `FEAT-USR-009` `RN-5`).
 *
 * **Rejected and named, never dropped in silence.** Quietly ignoring it
 * would let somebody finish the step believing they chose five things when
 * three were stored, and nothing would ever tell them otherwise.
 */
final class UnknownGenre extends \DomainException implements BusinessFailure
{
    /**
     * @param list<string> $codes
     */
    public static function among(array $codes): self
    {
        return new self(\sprintf('These genres are not in the catalogue: %s.', implode(', ', $codes)));
    }

    public function errorCode(): string
    {
        return 'UNKNOWN_GENRE';
    }

    public function kind(): FailureKind
    {
        return FailureKind::INVALID;
    }
}
