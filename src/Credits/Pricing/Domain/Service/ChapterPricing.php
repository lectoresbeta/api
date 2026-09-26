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
 * (`RN-3`). A correction moves credits; it neither creates nor destroys them,
 * which is why changing these constants cannot put the economy at risk.
 *
 * A domain service and not a method on an entity: the price belongs to no
 * single aggregate. It is computed from facts that arrive from `Work`.
 *
 * **The four numbers are levers, not literals** (`RN-7`). They arrive through
 * the constructor and `config/services.yaml` is what production passes; the
 * constants below are the calibration of record and the default, so a test
 * can state a price without restating the whole configuration. Correcting the
 * balance between reading and writing must not require a code change, and it
 * is safe to do: the price is a transfer, so moving it shifts no credits into
 * or out of the system.
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
     *
     * A questionnaire built through `FEAT-WRK-014` cannot get here with an
     * undeclared minimum — `C-14` made them mandatory — so this floor guards
     * the inputs that do **not** come from one: an import, a migration, a
     * questionnaire written before the rule existed.
     */
    public const WORDS_PER_UNBOUNDED_QUESTION = 25;

    public function __construct(
        private readonly int $wordsPerReadingCredit = self::WORDS_PER_READING_CREDIT,
        private readonly int $wordsPerWritingCredit = self::WORDS_PER_WRITING_CREDIT,
        private readonly int $wordsPerUnboundedQuestion = self::WORDS_PER_UNBOUNDED_QUESTION,
        private readonly int $minPrice = self::MIN_PRICE,
        private readonly int $maxPrice = self::MAX_PRICE,
    ) {
        if ($wordsPerReadingCredit < 1 || $wordsPerWritingCredit < 1 || $wordsPerUnboundedQuestion < 1) {
            throw new \InvalidArgumentException('A pricing lever cannot be zero or negative.');
        }

        // A configuration mistake here would silently price every correction
        // in the product, so it fails on the way in rather than showing up as
        // an inexplicable number weeks later.
        if ($minPrice < 1 || $maxPrice < $minPrice) {
            throw new \InvalidArgumentException('The price range is empty.');
        }
    }

    public function priceOf(int $wordCount, int $requiredWords): int
    {
        if ($wordCount < 0 || $requiredWords < 0) {
            throw new \InvalidArgumentException('Word counts cannot be negative.');
        }

        $reading = (int) ceil($wordCount / $this->wordsPerReadingCredit);
        $writing = (int) ceil($requiredWords / $this->wordsPerWritingCredit);

        return max($this->minPrice, min($this->maxPrice, $reading + $writing));
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
                ? $this->wordsPerUnboundedQuestion
                : $minimum;
        }

        return $total;
    }
}
