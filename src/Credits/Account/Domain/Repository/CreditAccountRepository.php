<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Domain\Repository;

use LectoresBeta\Credits\Account\Domain\Entity\CreditAccount;
use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;

interface CreditAccountRepository
{
    public function save(CreditAccount $account): void;

    public function ofUser(UserId $userId): ?CreditAccount;

    /**
     * The sum of every balance in the system.
     *
     * Not a statistic: `RN-11` says it must equal the taps minus the
     * unrecovered overdraft, and `FEAT-CRD-012` checks it periodically. If it
     * drifts, somebody is holding credits nobody paid for.
     */
    public function totalBalance(): int;
}
