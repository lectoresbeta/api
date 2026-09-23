<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\Credits;

use LectoresBeta\Credits\Account\Domain\Entity\CreditAccount;
use LectoresBeta\Credits\Account\Domain\Repository\CreditAccountRepository;
use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;

final class InMemoryCreditAccounts implements CreditAccountRepository
{
    /** @var array<string, CreditAccount> */
    private array $accounts = [];

    public function save(CreditAccount $account): void
    {
        $this->accounts[$account->userId()->value()] = $account;
    }

    public function ofUser(UserId $userId): ?CreditAccount
    {
        return $this->accounts[$userId->value()] ?? null;
    }

    public function totalBalance(): int
    {
        return array_sum(array_map(static fn (CreditAccount $a): int => $a->balance(), $this->accounts));
    }
}
