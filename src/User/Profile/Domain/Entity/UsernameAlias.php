<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Domain\Entity;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Account\Domain\ValueObject\Username;
use LectoresBeta\User\Profile\Domain\Enum\UsernameAliasReason;

/**
 * A username held in reserve for 30 days (`decision:0005`).
 *
 * The identity is the name itself, not a surrogate: the whole purpose is that
 * nobody else can take it while the row is alive.
 *
 * `userId` is nullable on purpose. An alias outlives the account that freed
 * it — a deleted account leaves its name blocked for 30 days — so the row
 * cannot depend on the `user` row still being there (`FEAT-USR-034`).
 */
class UsernameAlias
{
    private string $username;

    private ?string $userId = null;

    private UsernameAliasReason $reason;

    private \DateTimeImmutable $createdAt;

    private \DateTimeImmutable $expiresAt;

    private function __construct(
        Username $username,
        ?UserId $userId,
        UsernameAliasReason $reason,
        \DateTimeImmutable $now,
        \DateTimeImmutable $expiresAt,
    ) {
        $this->username = $username->value();
        $this->userId = $userId?->value();
        $this->reason = $reason;
        $this->createdAt = $now;
        $this->expiresAt = $expiresAt;
    }

    public static function afterRename(
        Username $username,
        UserId $userId,
        \DateTimeImmutable $now,
        int $reservationDays = 30,
    ): self {
        return new self(
            $username,
            $userId,
            UsernameAliasReason::USERNAME_CHANGED,
            $now,
            $now->modify(\sprintf('+%d days', $reservationDays)),
        );
    }

    public static function afterAccountDeletion(
        Username $username,
        \DateTimeImmutable $now,
        int $reservationDays = 30,
    ): self {
        return new self(
            $username,
            null,
            UsernameAliasReason::ACCOUNT_DELETED,
            $now,
            $now->modify(\sprintf('+%d days', $reservationDays)),
        );
    }

    public function username(): Username
    {
        return Username::fromString($this->username);
    }

    public function userId(): ?UserId
    {
        return null === $this->userId ? null : UserId::fromString($this->userId);
    }

    public function reason(): UsernameAliasReason
    {
        return $this->reason;
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    /**
     * An expired alias neither resolves nor blocks, even while its row is
     * still there waiting for the purge command (`FEAT-USR-036`).
     */
    public function isInForceAt(\DateTimeImmutable $moment): bool
    {
        return $moment < $this->expiresAt;
    }

    public function resolvesToProfileAt(\DateTimeImmutable $moment): bool
    {
        return $this->isInForceAt($moment)
            && $this->reason->resolvesToProfile()
            && null !== $this->userId;
    }

    /**
     * Taking your own name back renews the reservation on whatever you were
     * using instead (`FEAT-USR-034` `RN-1b`).
     */
    public function renew(\DateTimeImmutable $now, int $reservationDays = 30): void
    {
        $this->expiresAt = $now->modify(\sprintf('+%d days', $reservationDays));
    }
}
