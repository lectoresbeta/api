<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Domain\Entity;

use LectoresBeta\Credits\Pricing\Domain\ValueObject\WorkId;

/**
 * How much writing the questionnaire of a work demands (`FEAT-CRD-016`).
 *
 * It exists because the two facts that set a price arrive **separately and
 * in any order**. Without somewhere to keep the questionnaire's demand, a
 * chapter added after the questionnaire was configured would be priced as if
 * the work asked nothing, and would stay wrong until the author happened to
 * edit the questionnaire again.
 *
 * Two figures and not one: the last chapter answers every question, the rest
 * answer only those of scope `EVERY_CHAPTER` (`FEAT-WRK-014` `W-17`). With a
 * single total, an author would pay in chapter one for a question about the
 * ending.
 *
 * Like `ChapterPrice` it is a read model, rebuildable by replaying events.
 */
class WorkQuestionnaireDemand
{
    private string $workId;

    private int $version;

    private int $requiredWords;

    private int $requiredWordsForEveryChapter;

    private \DateTimeImmutable $updatedAt;

    public function __construct(
        WorkId $workId,
        int $version,
        int $requiredWords,
        int $requiredWordsForEveryChapter,
        \DateTimeImmutable $now,
    ) {
        $this->workId = $workId->value();
        $this->version = $version;
        $this->requiredWords = $requiredWords;
        $this->requiredWordsForEveryChapter = $requiredWordsForEveryChapter;
        $this->updatedAt = $now;
    }

    public function workId(): WorkId
    {
        return WorkId::fromString($this->workId);
    }

    public function version(): int
    {
        return $this->version;
    }

    /**
     * What the **last** chapter demands: every question is answered there.
     */
    public function requiredWords(): int
    {
        return $this->requiredWords;
    }

    /**
     * What any other chapter demands.
     */
    public function requiredWordsForEveryChapter(): int
    {
        return $this->requiredWordsForEveryChapter;
    }

    /**
     * Returns `false` when the version offered is not newer.
     *
     * A queue redelivers, and it does not promise order. Applying version 2
     * after version 3 would quietly take the price back to what the author
     * already changed, so the version decides — not the arrival.
     */
    public function update(int $version, int $requiredWords, int $requiredWordsForEveryChapter, \DateTimeImmutable $now): bool
    {
        if ($version <= $this->version) {
            return false;
        }

        $this->version = $version;
        $this->requiredWords = $requiredWords;
        $this->requiredWordsForEveryChapter = $requiredWordsForEveryChapter;
        $this->updatedAt = $now;

        return true;
    }
}
