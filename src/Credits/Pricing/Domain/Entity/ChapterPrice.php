<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Domain\Entity;

use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Credits\Pricing\Domain\Service\ChapterPricing;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\ChapterId;
use LectoresBeta\Credits\Pricing\Domain\ValueObject\WorkId;

/**
 * What one chapter currently costs to correct (`FEAT-CRD-016`).
 *
 * A read model: it is fed by `ChapterContentUpdated` and `QuestionnaireUpdated`
 * and can be rebuilt from scratch by replaying them. It is the source of
 * truth for nothing — which is why a wrong row here is an inconvenience, not
 * a loss of money. What a reader is actually owed is frozen in
 * `CorrectionPrice` the moment they start (`RN-4`), and nothing that happens
 * to this row afterwards can reach them.
 *
 * It keeps `position` because the price of a chapter depends on whether it is
 * the last one: that is where the questions of scope `LAST_CHAPTER` get
 * answered (`FEAT-WRK-014` `W-17`).
 */
class ChapterPrice
{
    private string $chapterId;

    private string $workId;

    private string $authorId;

    private int $position;

    private int $wordCount = 0;

    private int $requiredWords = 0;

    private int $price;

    /**
     * Whether the chapter admits a correction right now. It lives on the row
     * so that a change can be **noticed**: without the previous answer there
     * is nothing to compare against, and every recalculation would either
     * publish an event or none at all.
     */
    private bool $correctable = false;

    /**
     * Cuántas correcciones de este capítulo puede pagar el autor, con el tope
     * de `decision:0008`. Sale del contexto porque el catálogo ordena con
     * ello; el saldo y el precio con los que se calcula, no.
     */
    private int $affordableCorrections = 0;

    /**
     * Cuándo se contó por última vez la respuesta a «¿se puede corregir?».
     *
     * Nulo significa **nunca**, y eso es distinto de «no ha cambiado». Un
     * capítulo que su autor no ha podido pagar nunca nace ya en `false`, así
     * que sin esta marca la primera respuesta coincidiría con el valor
     * inicial, no se publicaría nada, y `Feedback` se quedaría sin saber
     * —dejándolo pasar por omisión—. Es el agujero por el que `RN-2` de
     * `FEAT-CRD-018` se caía: la puerta solo se cerraba si alguna vez había
     * estado abierta.
     */
    private ?\DateTimeImmutable $correctabilityAnnouncedAt = null;

    private \DateTimeImmutable $updatedAt;

    public function __construct(
        ChapterId $chapterId,
        WorkId $workId,
        UserId $authorId,
        int $position,
        int $wordCount,
        int $requiredWords,
        ChapterPricing $pricing,
        \DateTimeImmutable $now,
    ) {
        $this->chapterId = $chapterId->value();
        $this->workId = $workId->value();
        $this->authorId = $authorId->value();
        $this->position = $position;
        $this->wordCount = $wordCount;
        $this->requiredWords = $requiredWords;
        $this->price = $pricing->priceOf($wordCount, $requiredWords);
        $this->updatedAt = $now;
    }

    public function chapterId(): ChapterId
    {
        return ChapterId::fromString($this->chapterId);
    }

    public function workId(): WorkId
    {
        return WorkId::fromString($this->workId);
    }

    public function authorId(): UserId
    {
        return UserId::fromString($this->authorId);
    }

    public function position(): int
    {
        return $this->position;
    }

    public function price(): int
    {
        return $this->price;
    }

    public function wordCount(): int
    {
        return $this->wordCount;
    }

    public function requiredWords(): int
    {
        return $this->requiredWords;
    }

    public function isCorrectable(): bool
    {
        return $this->correctable;
    }

    public function affordableCorrections(): int
    {
        return $this->affordableCorrections;
    }

    /**
     * Returns whether this is news. Only a change is worth an event: the
     * price of a chapter is recomputed far more often than its answer to
     * «can this be corrected?» actually moves.
     *
     * **La primera vez siempre es noticia**, aunque el valor coincida con el
     * inicial. Nadie lo ha oído todavía, y quien escucha no puede distinguir
     * «no ha cambiado» de «nunca me lo han dicho».
     */
    public function updateCorrectability(bool $correctable, int $affordableCorrections, \DateTimeImmutable $now): bool
    {
        $announced = null !== $this->correctabilityAnnouncedAt;

        if ($announced && $correctable === $this->correctable && $affordableCorrections === $this->affordableCorrections) {
            return false;
        }

        $this->correctable = $correctable;
        $this->affordableCorrections = $affordableCorrections;
        $this->correctabilityAnnouncedAt = $now;
        $this->updatedAt = $now;

        return true;
    }

    /**
     * The chapter holds a different text, or sits at a different place in the
     * work.
     */
    public function updateContent(int $position, int $wordCount, ChapterPricing $pricing, \DateTimeImmutable $now): void
    {
        $this->position = $position;
        $this->wordCount = $wordCount;
        $this->reprice($pricing, $now);
    }

    /**
     * How many words the questionnaire demands **in this chapter**. Which
     * number that is belongs to `WorkPricing`: this row knows its own
     * position, not where the work ends.
     */
    public function applyDemand(int $requiredWords, ChapterPricing $pricing, \DateTimeImmutable $now): void
    {
        $this->requiredWords = $requiredWords;
        $this->reprice($pricing, $now);
    }

    private function reprice(ChapterPricing $pricing, \DateTimeImmutable $now): void
    {
        $this->price = $pricing->priceOf($this->wordCount, $this->requiredWords);
        $this->updatedAt = $now;
    }
}
