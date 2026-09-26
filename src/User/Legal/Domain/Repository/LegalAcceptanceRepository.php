<?php

declare(strict_types=1);

namespace LectoresBeta\User\Legal\Domain\Repository;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Legal\Domain\Entity\LegalAcceptance;

interface LegalAcceptanceRepository
{
    public function save(LegalAcceptance $acceptance): void;

    /**
     * Everything this person has ever accepted, newest first. Consent has to
     * be readable back (`FEAT-USR-024` `RN-6`).
     *
     * @return list<LegalAcceptance>
     */
    public function ofUser(UserId $userId): array;
}
