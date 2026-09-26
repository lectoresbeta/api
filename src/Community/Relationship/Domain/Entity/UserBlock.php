<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Relationship\Domain\Entity;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Relationship\Domain\Exception\BlockRefused;
use LectoresBeta\Community\Relationship\Domain\ValueObject\UserBlockId;

/**
 * One person blocking another (`FEAT-COM-034`).
 *
 * A block beats every privacy setting: no combination of them gives a blocked
 * person access (`FEAT-USR-038` `RN-5`).
 *
 * `Community` publishes `UserBlocked` and **does nothing else** (`RN-11`): it
 * does not revoke beta-reader access and it does not touch credits. Each
 * context decides what a block means in its own model.
 *
 * That a block can be used to stop somebody finishing a correction in
 * progress is a known, accepted abuse: the right to block weighs more than
 * the rare bad-faith use. It is neither recorded nor penalised.
 */
class UserBlock
{
    private string $id;

    private string $blockerId;

    private string $blockedId;

    private \DateTimeImmutable $createdAt;

    public function __construct(
        UserBlockId $id,
        MemberId $blockerId,
        MemberId $blockedId,
        \DateTimeImmutable $now,
    ) {
        // Una regla de negocio, no una comprobación defensiva: quien la
        // incumple recibe un `422` con su código, no un `500`.
        if ($blockerId->value() === $blockedId->value()) {
            throw BlockRefused::yourself();
        }

        $this->id = $id->value();
        $this->blockerId = $blockerId->value();
        $this->blockedId = $blockedId->value();
        $this->createdAt = $now;
    }

    public function id(): UserBlockId
    {
        return UserBlockId::fromString($this->id);
    }

    public function blockerId(): MemberId
    {
        return MemberId::fromString($this->blockerId);
    }

    public function blockedId(): MemberId
    {
        return MemberId::fromString($this->blockedId);
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
