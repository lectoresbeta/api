<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Domain\Service;

/**
 * The price of correcting one chapter (`decision:0006`, rule 1;
 * `FEAT-CRD-016`).
 *
 *     price = ceil(words / 1000) + ceil(required words / 100)
 *
 * Two terms, **reading** and **writing**, clamped between 2 and 20.
 *
 * The second term is what makes the model simple: a single number captures
 * the whole demand of the questionnaire. There is no need to weigh how many
 * questions there are, or of what kind — the author already declared how much
 * work they want when they set the minimum word counts.
 *
 * Price is also **reward**: the author pays exactly what the reader earns
 * (`RN-6`). A correction moves credits; it neither creates nor destroys them,
 * which is why changing these constants cannot put the economy at risk.
 *
 * A domain service and not a method on an entity: the price belongs to no
 * single aggregate. It is computed from facts that arrive from `Work`.
 */
final class ChapterPricing
{
    public const MIN_PRICE = 2;
    public const MAX_PRICE = 20;

    public const WORDS_PER_READING_CREDIT = 1000;
    public const WORDS_PER_WRITING_CREDIT = 100;

    /**
     * A question with no declared minimum counts as this many words
     * (`decision:0006`). Without a floor, a questionnaire that demands
     * nothing would score zero on the writing term and a whole novel would
     * be corrected for two credits.
     */
    public const WORDS_PER_UNBOUNDED_QUESTION = 25;

    public function priceOf(int $wordCount, int $requiredWords): int
    {
        if ($wordCount < 0 || $requiredWords < 0) {
            throw new \InvalidArgumentException('Word counts cannot be negative.');
        }

        $reading = (int) ceil($wordCount / self::WORDS_PER_READING_CREDIT);
        $writing = (int) ceil($requiredWords / self::WORDS_PER_WRITING_CREDIT);

        return max(self::MIN_PRICE, min(self::MAX_PRICE, $reading + $writing));
    }

    /**
     * Adds up what the questionnaire demands, applying the floor to every
     * question that declares no minimum.
     *
     * @param list<int|null> $minimumWordsPerQuestion
     */
    public function requiredWords(array $minimumWordsPerQuestion): int
    {
        $total = 0;

        foreach ($minimumWordsPerQuestion as $minimum) {
            $total += (null === $minimum || $minimum <= 0)
                ? self::WORDS_PER_UNBOUNDED_QUESTION
                : $minimum;
        }

        return $total;
    }
}
