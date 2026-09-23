<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Claim\Domain\Repository;

use LectoresBeta\Moderation\Claim\Domain\Entity\Claim;
use LectoresBeta\Moderation\Claim\Domain\Enum\ClaimTargetType;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\ClaimId;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;

interface ClaimRepository
{
    public function save(Claim $claim): void;

    public function ofId(ClaimId $id): ?Claim;

    /**
     * The moderators' queue, oldest first: a complaint that waits is the
     * failure mode this whole context exists to avoid.
     *
     * @return list<Claim>
     */
    public function pending(int $limit = 50): array;

    /**
     * How many claims this person has filed inside the period. Three a month
     * (`MOD-2`), and a claim entered by a moderator on their behalf counts
     * too (`MOD-45`) — otherwise email is the way around the limit.
     */
    public function countFiledSince(PartyId $reporterId, \DateTimeImmutable $since): int;

    public function alreadyFiled(PartyId $reporterId, ClaimTargetType $targetType, string $targetId): bool;

    /**
     * @return list<Claim>
     */
    public function about(ClaimTargetType $targetType, string $targetId): array;
}
