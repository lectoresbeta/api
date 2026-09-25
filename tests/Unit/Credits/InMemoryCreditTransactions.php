<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\Credits;

use LectoresBeta\Credits\Account\Domain\Entity\CreditTransaction;
use LectoresBeta\Credits\Account\Domain\Enum\CreditTransactionReason;
use LectoresBeta\Credits\Account\Domain\Repository\CreditTransactionRepository;
use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;

final class InMemoryCreditTransactions implements CreditTransactionRepository
{
    /** @var list<CreditTransaction> */
    private array $movements = [];

    public function add(CreditTransaction $transaction): void
    {
        $this->movements[] = $transaction;
    }

    /**
     * @return list<CreditTransaction>
     */
    public function all(): array
    {
        return $this->movements;
    }

    public function historyOf(
        UserId $userId,
        int $limit = 50,
        int $offset = 0,
        ?CreditTransactionReason $reason = null,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null,
    ): array {
        return array_values(array_filter(
            $this->movements,
            static fn (CreditTransaction $m): bool => $m->userId()->equals($userId)
                && (null === $reason || $m->reason() === $reason)
                && (null === $from || $m->occurredAt() >= $from)
                && (null === $to || $m->occurredAt() <= $to),
        ));
    }

    public function sumAfter(UserId $userId, CreditTransaction $movement): int
    {
        return array_sum(array_map(
            static fn (CreditTransaction $m): int => $m->amount(),
            array_filter(
                $this->historyOf($userId),
                static fn (CreditTransaction $m): bool => $m->occurredAt() > $movement->occurredAt(),
            ),
        ));
    }

    public function balanceOf(UserId $userId): int
    {
        return array_sum(array_map(
            static fn (CreditTransaction $m): int => $m->amount(),
            $this->historyOf($userId),
        ));
    }

    public function hasMovementWithReason(UserId $userId, CreditTransactionReason $reason): bool
    {
        foreach ($this->historyOf($userId) as $movement) {
            if ($movement->reason() === $reason) {
                return true;
            }
        }

        return false;
    }

    public function ofCorrection(string $correctionId): array
    {
        return array_values(array_filter(
            $this->movements,
            static fn (CreditTransaction $m): bool => ($m->metadata()['correctionId'] ?? null) === $correctionId,
        ));
    }

    public function totalIssued(): int
    {
        return array_sum(array_map(
            static fn (CreditTransaction $m): int => $m->reason()->isTap() ? $m->amount() : 0,
            $this->movements,
        ));
    }
}
