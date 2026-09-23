<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Rating\Domain\Entity;

use LectoresBeta\Feedback\Correction\Domain\ValueObject\ReaderId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\WorkId;
use LectoresBeta\Feedback\Rating\Domain\ValueObject\WorkRatingId;

/**
 * A beta reader's rating of a work. One per reader and work.
 *
 * The scale is 1 to 5. `F-3` left it open; five stars is the one readers
 * already know, and nothing downstream depends on the range — `Community`
 * averages whatever arrives.
 *
 * It feeds the rankings, never the catalogue: the catalogue deliberately does
 * not reward quality (`decision:0008`).
 */
class WorkRating
{
    public const MIN_VALUE = 1;
    public const MAX_VALUE = 5;

    private string $id;

    private string $workId;

    private string $readerId;

    private int $value;

    private \DateTimeImmutable $ratedAt;

    private \DateTimeImmutable $updatedAt;

    public function __construct(
        WorkRatingId $id,
        WorkId $workId,
        ReaderId $readerId,
        int $value,
        \DateTimeImmutable $now,
    ) {
        $this->id = $id->value();
        $this->workId = $workId->value();
        $this->readerId = $readerId->value();
        $this->value = $this->guardRange($value);
        $this->ratedAt = $now;
        $this->updatedAt = $now;
    }

    public function id(): WorkRatingId
    {
        return WorkRatingId::fromString($this->id);
    }

    public function workId(): WorkId
    {
        return WorkId::fromString($this->workId);
    }

    public function readerId(): ReaderId
    {
        return ReaderId::fromString($this->readerId);
    }

    public function value(): int
    {
        return $this->value;
    }

    public function change(int $value, \DateTimeImmutable $now): void
    {
        $this->value = $this->guardRange($value);
        $this->updatedAt = $now;
    }

    private function guardRange(int $value): int
    {
        if ($value < self::MIN_VALUE || $value > self::MAX_VALUE) {
            throw new \DomainException(\sprintf('A rating goes from %d to %d.', self::MIN_VALUE, self::MAX_VALUE));
        }

        return $value;
    }
}
