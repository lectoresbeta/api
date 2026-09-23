<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Application\Contract;

/**
 * What the author asks of whoever corrects one chapter, and whether that
 * chapter may be corrected at all.
 *
 * It answers in one go the three things another context needs before opening
 * a correction panel: **whose work it is**, **whether the door is open**, and
 * **what is being asked**. Three calls would be three chances for the answers
 * to come from three different instants.
 *
 * Note what it does not carry: not a word of the chapter's text, and no
 * credit figure. What correcting this is worth belongs to `Credits`, and
 * `Work` has no business knowing it either.
 *
 * `questions` is already filtered for **this** chapter: a question of scope
 * `LAST_CHAPTER` does not appear in chapter one (`FEAT-WRK-014` `W-17`).
 * Which chapter is the last is something only `Work` can answer, so the
 * filtering happens here rather than in whoever asks.
 */
final readonly class CorrectionBrief
{
    /**
     * @param list<BriefQuestion> $questions
     */
    public function __construct(
        public string $chapterId,
        public string $workId,
        public string $authorId,
        public bool $openForCorrection,
        public string $accessMode,
        public bool $adultsOnly,
        public int $questionnaireVersion,
        public array $questions,
    ) {
    }

    /**
     * A work whose author has not written a questionnaire yet cannot be
     * corrected: there is nothing to answer, and the price of the writing
     * would be zero.
     */
    public function hasQuestions(): bool
    {
        return [] !== $this->questions;
    }
}
