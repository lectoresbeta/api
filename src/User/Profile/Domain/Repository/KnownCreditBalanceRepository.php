<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Domain\Repository;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Profile\Domain\Entity\KnownCreditBalance;

interface KnownCreditBalanceRepository
{
    public function save(KnownCreditBalance $balance): void;

    public function ofUser(UserId $userId): ?KnownCreditBalance;
}
