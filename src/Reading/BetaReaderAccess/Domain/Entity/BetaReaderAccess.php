<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderAccess\Domain\Entity;

use LectoresBeta\Reading\BetaReaderAccess\Domain\Enum\AccessSource;
use LectoresBeta\Reading\BetaReaderAccess\Domain\Exception\AuthorCannotBeBetaReader;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\AuthorId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\BetaReaderAccessId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\WorkId;

/**
 * Somebody's right to read a work as a beta reader.
 *
 * One live access per (reader, work) (`RN-2`), enforced by a partial unique
 * index: a plain unique key would stop access from ever being granted again
 * after a revocation.
 *
 * Since `decision:0006` removed credit holds, granting access commits
 * nothing: no reservation, no compensation, no waiting on `Credits`. The
 * balance is only checked when a correction starts, and even then only as
 * advice.
 */
class BetaReaderAccess
{
    private string $id;

    private string $workId;

    private string $readerId;

    private string $authorId;

    private AccessSource $source;

    private \DateTimeImmutable $grantedAt;

    /**
     * Whether this access has been **earned** by delivering a correction of
     * the work (`FEAT-RDG-001` `RN-5`).
     *
     * A boolean and not a count: what it decides is one thing only — whether
     * discarding a draft may take the access away again — and for that, one
     * delivered correction is as good as twenty.
     */
    private bool $earned = false;

    private ?\DateTimeImmutable $revokedAt = null;

    public function __construct(
        BetaReaderAccessId $id,
        WorkId $workId,
        ReaderId $readerId,
        AuthorId $authorId,
        AccessSource $source,
        \DateTimeImmutable $now,
    ) {
        if ($readerId->value() === $authorId->value()) {
            throw AuthorCannotBeBetaReader::ofWork($workId->value());
        }

        $this->id = $id->value();
        $this->workId = $workId->value();
        $this->readerId = $readerId->value();
        $this->authorId = $authorId->value();
        $this->source = $source;
        $this->grantedAt = $now;
    }

    public function id(): BetaReaderAccessId
    {
        return BetaReaderAccessId::fromString($this->id);
    }

    public function workId(): WorkId
    {
        return WorkId::fromString($this->workId);
    }

    public function readerId(): ReaderId
    {
        return ReaderId::fromString($this->readerId);
    }

    public function authorId(): AuthorId
    {
        return AuthorId::fromString($this->authorId);
    }

    public function source(): AccessSource
    {
        return $this->source;
    }

    public function isLive(): bool
    {
        return null === $this->revokedAt;
    }

    public function isEarned(): bool
    {
        return $this->earned;
    }

    /**
     * The reader delivered a correction of this work. From here on, walking
     * away from another draft does not take the access with it: they did the
     * work once, and that is what the access records.
     */
    public function markEarned(): void
    {
        $this->earned = true;
    }

    /**
     * Whether abandoning a draft should undo this access
     * (`FEAT-RDG-001` `RN-5`).
     *
     * Two conditions, and both matter. It has to have come from **this**
     * route — a request the author accepted, or an invitation they sent, is
     * not undone because somebody discarded a draft — and the reader must
     * never have delivered anything.
     */
    public function isUndoneByWalkingAway(): bool
    {
        return AccessSource::PUBLIC_JOIN === $this->source && !$this->earned;
    }

    /**
     * Changing the work's access mode does **not** come through here
     * (`RN-5`): what is granted stays granted.
     */
    public function revoke(\DateTimeImmutable $now): void
    {
        $this->revokedAt ??= $now;
    }
}
