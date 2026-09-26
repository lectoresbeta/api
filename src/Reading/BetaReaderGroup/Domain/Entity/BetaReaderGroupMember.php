<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderGroup\Domain\Entity;

use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Reading\BetaReaderGroup\Domain\ValueObject\BetaReaderGroupId;

/**
 * Alguien apuntado en la agenda de un autor (`FEAT-RDG-007`).
 *
 * La clave es el par: una persona está o no está en un grupo, y estar dos
 * veces no significa nada. Por eso añadir es idempotente (`RN-8`).
 */
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

    public function addedAt(): \DateTimeImmutable
    {
        return $this->addedAt;
    }
}
