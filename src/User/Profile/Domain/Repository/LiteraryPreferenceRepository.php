<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Domain\Repository;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Profile\Domain\Entity\LiteraryPreference;

interface LiteraryPreferenceRepository
{
    public function add(LiteraryPreference $preference): void;

    /**
     * @return list<string> the genre codes, in no particular order
     */
    public function codesOf(UserId $userId): array;

    /**
     * Wipes the selection so a new one can replace it.
     *
     * Replacing and not merging: choosing genres is picking a set, and
     * somebody who removes one expects it gone (`FEAT-USR-009`).
     */
    public function removeAllOf(UserId $userId): void;
}
