<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\Credits;

use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Credits\Overdraft\Domain\Entity\OverdraftGrant;
use LectoresBeta\Credits\Overdraft\Domain\Repository\OverdraftGrantRepository;

final class InMemoryOverdraftGrants implements OverdraftGrantRepository
{
    /** @var array<string, OverdraftGrant> */
    private array $grants = [];

    public function save(OverdraftGrant $grant): void
    {
        $this->grants[$grant->id()->value()] = $grant;
    }

    public function countInPeriod(string $quotaPeriod): int
    {
        return \count(array_filter(
            $this->grants,
            static fn (OverdraftGrant $grant): bool => $grant->quotaPeriod() === $quotaPeriod,
        ));
    }

    public function hasGrantFor(UserId $authorId): bool
    {
        foreach ($this->grants as $grant) {
            if ($grant->authorId()->equals($authorId)) {
                return true;
            }
        }

        return false;
    }

    public function usableOf(UserId $authorId, \DateTimeImmutable $moment): ?OverdraftGrant
    {
        foreach ($this->grants as $grant) {
            if ($grant->authorId()->equals($authorId) && $grant->isUsableAt($moment)) {
                return $grant;
            }
        }

        return null;
    }

    public function unsettledOf(UserId $authorId): array
    {
        return array_values(array_filter(
            $this->grants,
            static fn (OverdraftGrant $grant): bool => $grant->authorId()->equals($authorId) && !$grant->isSettled(),
        ));
    }

    public function recovery(): array
    {
        $used = array_filter($this->grants, static fn (OverdraftGrant $grant): bool => $grant->wasUsed());

        return [
            'granted' => \count($used),
            'settled' => \count(array_filter($used, static fn (OverdraftGrant $grant): bool => $grant->isSettled())),
        ];
    }
}
