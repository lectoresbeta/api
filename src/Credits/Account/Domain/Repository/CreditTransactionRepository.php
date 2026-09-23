<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Domain\Repository;

use LectoresBeta\Credits\Account\Domain\Entity\CreditTransaction;
use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;

/**
 * Movements are immutable, so this contract offers **no update and no
 * delete** (`RN-2`). Correcting one means adding another.
 */
interface CreditTransactionRepository
{
    public function add(CreditTransaction $transaction): void;

    /**
     * @return list<CreditTransaction>
     */
    public function historyOf(UserId $userId, int $limit = 50, int $offset = 0): array;

    /**
     * Recomputes a balance from its movements. It exists so that the running
     * total on the account can be checked against the only thing that is
     * really true (`RN-1`).
     */
    public function balanceOf(UserId $userId): int;

    /**
     * Everything the economy has ever issued: the welcome grants, the
     * invitation rewards and any manual adjustment. The other half of the
     * invariant in `RN-11`.
     */
    public function totalIssued(): int;
}
