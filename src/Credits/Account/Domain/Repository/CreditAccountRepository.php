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
     * Not a statistic: `RN-11` says it must equal what the taps issued, and
     * `FEAT-CRD-012` checks it periodically. If it drifts, somebody is
     * holding credits nobody paid for.
     */
    public function totalBalance(): int;

    /**
     * Cómo está repartido el saldo, en una sola consulta (`FEAT-CRD-012`).
     *
     * Las cuatro cifras juntas y no cuatro métodos porque se leen juntas y
     * describen **el mismo instante**: entre una consulta y la siguiente una
     * corrección puede cambiar dos de ellas, y un panel que mezcle dos
     * instantes miente sobre los dos.
     *
     * `deepestDebt` es cero o negativo, nunca positivo: es el saldo más bajo
     * que hay ahora mismo.
     *
     * @return array{total: int, atZeroOrBelow: int, inDebt: int, deepestDebt: int}
     */
    public function balanceSpread(): array;
}
