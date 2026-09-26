<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Domain\Repository;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;

interface LiteraryPreferenceRepository
{
    /**
     * @return list<string> the genre codes, in no particular order
     */
    public function codesOf(UserId $userId): array;

    /**
     * Leaves the selection exactly as given.
     *
     * **Replacing and not merging**: choosing genres is picking a set, and
     * somebody who removes one expects it gone (`FEAT-USR-009` `RN-1`). One
     * operation and not an `add`/`removeAll` pair because the two halves have
     * to happen together, and because what is already there must not be
     * deleted and reinserted just for being sent again.
     *
     * @param list<string> $codes
     */
    public function replaceAllOf(UserId $userId, array $codes, \DateTimeImmutable $now): void;
}
