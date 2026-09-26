<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Application\Handler;

use LectoresBeta\Credits\Account\Application\Query\GetCreditBalance;
use LectoresBeta\Credits\Account\Domain\Repository\CreditAccountRepository;
use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;

/**
 * The balance (`FEAT-CRD-001`).
 *
 * **No account means zero, not «not found»** (`RN-2`). That is the normal
 * state of somebody who registered and has not activated yet, and the header
 * of the application asks for this the moment they sign in — a `404` there
 * would break the layout for every new user.
 *
 * One number, because nothing is ever held back (`decision:0006` §3): there
 * is no «available» balance to be different from a «total» one. And it may be
 * negative, which any consumer has to be ready for.
 */
final readonly class GetCreditBalanceHandler
{
    public function __construct(private CreditAccountRepository $accounts)
    {
    }

    public function __invoke(GetCreditBalance $query): int
    {
        return $this->accounts->ofUser(UserId::fromString($query->userId))?->balance() ?? 0;
    }
}
