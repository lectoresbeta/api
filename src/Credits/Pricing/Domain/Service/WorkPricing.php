<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Domain\Service;

use LectoresBeta\Credits\Pricing\Domain\Entity\ChapterPrice;
use LectoresBeta\Credits\Pricing\Domain\Entity\WorkQuestionnaireDemand;

/**
 * Spreading the demand of one questionnaire over the chapters of a work
 * (`FEAT-CRD-016` `RN-2`).
 *
 * One questionnaire, many prices. The rule is short and it is the reason the
 * questionnaire carries two totals: **the last chapter answers everything,
 * every other chapter answers only what applies to it**
 * (`FEAT-WRK-014` `W-17`). An author who asks «how did the ending feel?»
 * should not pay for that question in chapter one, where nobody can answer
 * it.
 *
 * Which chapter is the last is decided by the highest position this context
 * knows about, not by asking `Work` — that would be reaching into another
 * context's model for a number that arrives in its events anyway. The cost
 * is that a chapter still in flight briefly leaves its predecessor looking
 * like the last one; the next event puts it right, and this is a read model
 * where being briefly behind costs nothing.
 *
 * A domain service because the rule belongs to no single row: it needs to see
 * all of them at once to know where the work ends.
 */
final class WorkPricing
{
    public function __construct(private readonly ChapterPricing $pricing)
    {
    }

    /**
     * Devuelve **los capítulos cuyo precio ha cambiado**, que son los que hay
     * que anunciar: la insignia de la tarjeta enseña esa cifra
     * ([`FEAT-CRD-013`](../../../../../docs/features/credits/FEAT-CRD-013-work-credit-badge.md)),
     * y repreciar es mucho más frecuente que cambiar de precio.
     *
     * @param list<ChapterPrice> $chapters
     *
     * @return list<ChapterPrice>
     */
    public function reprice(array $chapters, ?WorkQuestionnaireDemand $demand, \DateTimeImmutable $now): array
    {
        $lastPosition = 0;

        foreach ($chapters as $chapter) {
            $lastPosition = max($lastPosition, $chapter->position());
        }

        $changed = [];

        foreach ($chapters as $chapter) {
            $before = $chapter->price();

            $chapter->applyDemand(
                $this->demandOn($chapter->position(), $lastPosition, $demand),
                $this->pricing,
                $now,
            );

            if ($chapter->price() !== $before) {
                $changed[] = $chapter;
            }
        }

        return $changed;
    }

    /**
     * A work whose questionnaire has not arrived yet demands nothing, and its
     * chapters are priced on their length alone — never below the floor of
     * two credits. It is a temporary state, corrected the moment
     * `QuestionnaireUpdated` shows up, and preferable to refusing to price a
     * chapter at all.
     */
    private function demandOn(int $position, int $lastPosition, ?WorkQuestionnaireDemand $demand): int
    {
        if (null === $demand) {
            return 0;
        }

        return $position === $lastPosition
            ? $demand->requiredWords()
            : $demand->requiredWordsForEveryChapter();
    }
}
