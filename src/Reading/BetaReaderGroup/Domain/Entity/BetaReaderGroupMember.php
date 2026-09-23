<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderGroup\Domain\Entity;

use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Reading\BetaReaderGroup\Domain\ValueObject\BetaReaderGroupId;

class BetaReaderGroupMember
{
    private string $groupId;

    private string $readerId;

    private \DateTimeImmutable $addedAt;

    public function __construct(BetaReaderGroupId $groupId, ReaderId $readerId, \DateTimeImmutable $now)
    {
        $this->groupId = $groupId->value();
        $this->readerId = $readerId->value();
        $this->addedAt = $now;
    }

    public function groupId(): BetaReaderGroupId
    {
        return BetaReaderGroupId::fromString($this->groupId);
    }

    public function readerId(): ReaderId
    {
        return ReaderId::fromString($this->readerId);
    }
}
