<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Domain\Service;

/**
 * Whether a chapter admits a correction right now (`FEAT-CRD-009` `RN-1`,
 * `RN-6`, `RN-8`).
 *
 * Two conditions, and neither sets a credit aside: **the author's balance
 * covers the price**, and **the chapter is not already being corrected by
 * three people**.
 *
 * The second is what bounds the debt a race can cause. Nothing is held, so
 * three readers can start on a balance that covers one and all three will be
 * paid (`RN-5`); capping the simultaneous corrections caps that overdraft at
 * a couple of corrections without stopping a popular chapter dead.
 *
 * The answer this produces is **a boolean that leaves the context**, and that
 * is deliberate: `Feedback` decides whether to open the panel without ever
 * learning a balance or a price.
 */
final class CorrectabilityPolicy
{
    /**
     * Three, argued in `FEAT-CRD-009`: with no cap the debt is unbounded,
     * with two a reader who arrives third is turned away too often.
     */
    public const MAX_OPEN_CORRECTIONS = 3;

    public function allows(int $authorBalance, int $price, int $openCorrections): bool
    {
        return $authorBalance >= $price && $openCorrections < self::MAX_OPEN_CORRECTIONS;
    }
}
