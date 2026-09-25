<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Claim\Domain\Repository;

use LectoresBeta\Moderation\Claim\Domain\Entity\ClaimRestriction;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;

interface ClaimRestrictionRepository
{
    public function save(ClaimRestriction $restriction): void;

    public function ofUser(PartyId $userId): ?ClaimRestriction;
}
