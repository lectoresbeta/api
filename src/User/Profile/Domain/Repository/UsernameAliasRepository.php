<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Domain\Repository;

use LectoresBeta\User\Account\Domain\ValueObject\Username;
use LectoresBeta\User\Profile\Domain\Entity\UsernameAlias;

interface UsernameAliasRepository
{
    public function save(UsernameAlias $alias): void;

    public function ofUsername(Username $username): ?UsernameAlias;

    /**
     * Whether the name is held right now. An expired alias neither resolves
     * nor blocks, even while its row is still waiting for the purge
     * (`FEAT-USR-036`), so the check is by date and not by existence.
     */
    public function isHeldAt(Username $username, \DateTimeImmutable $moment): bool;

    /**
     * @return list<UsernameAlias>
     */
    public function expiredAt(\DateTimeImmutable $moment, int $limit = 500): array;

    public function purge(UsernameAlias $alias): void;
}
