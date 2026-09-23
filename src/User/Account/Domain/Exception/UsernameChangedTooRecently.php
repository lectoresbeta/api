<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Domain\Exception;

/**
 * The username can be changed once every 30 days (`FEAT-USR-034` `RN-2`).
 *
 * The cooldown is not an anti-abuse afterthought: every change reserves the
 * old name as an alias for 30 days, so without it one account could sit on an
 * unbounded number of names.
 */
final class UsernameChangedTooRecently extends \DomainException
{
    public static function availableOn(\DateTimeImmutable $moment): self
    {
        return new self(\sprintf('The username can be changed again on %s.', $moment->format('Y-m-d')));
    }
}
