<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureDetails;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * The username can be changed once every 30 days (`FEAT-USR-034` `RN-1`).
 *
 * The cooldown is not an anti-abuse afterthought: every change reserves the
 * old name as an alias for 30 days, so without it one account could sit on an
 * unbounded number of names.
 *
 * It carries **when**, and that is the point of it being a `429` rather than
 * a conflict. A client that cannot tell the date will keep the form enabled
 * and let somebody try again tomorrow, and the day after, which is exactly
 * the behaviour the limit exists to stop.
 */
final class UsernameChangedTooRecently extends \DomainException implements BusinessFailure, FailureDetails
{
    private function __construct(private readonly \DateTimeImmutable $availableOn, string $message)
    {
        parent::__construct($message);
    }

    public static function availableOn(\DateTimeImmutable $moment): self
    {
        return new self($moment, \sprintf('The username can be changed again on %s.', $moment->format('Y-m-d')));
    }

    public function failureDetails(): array
    {
        return ['availableOn' => $this->availableOn->format(\DATE_ATOM)];
    }

    public function errorCode(): string
    {
        return 'USERNAME_CHANGE_TOO_SOON';
    }

    public function kind(): FailureKind
    {
        return FailureKind::RATE_LIMITED;
    }
}
