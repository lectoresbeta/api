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

    /**
     * El tope de capacidad de `decision:0008`. Un autor con saldo para
     * cuarenta correcciones no debe ocupar el catálogo cuarenta veces más
     * que uno con saldo para diez.
     */
    public const MAX_AFFORDABLE_CORRECTIONS = 10;

    /**
     * Cuántas correcciones de este capítulo puede pagar su autor ahora mismo,
     * con tope ([`decision:0008`](../../../../../docs/decisions/0008-catalogue-ordering.md)).
     *
     * **No es un saldo ni un precio**: es la respuesta a «¿cuánto trabajo
     * produce enseñar esta obra?», que es lo que el catálogo necesita para
     * ordenar. Un número derivado y acotado sale de este contexto; las dos
     * cifras con las que se calcula, no.
     *
     * El tope impide que un autor con mucho saldo monopolice el catálogo, y
     * de paso acota lo que este número cuenta sobre su cuenta: por encima de
     * diez, todos los autores se parecen.
     */
    public function affordableCorrections(int $authorBalance, int $price): int
    {
        if ($authorBalance < $price || $price < 1) {
            return 0;
        }

        return min(intdiv($authorBalance, $price), self::MAX_AFFORDABLE_CORRECTIONS);
    }

    public function allows(int $authorBalance, int $price, int $openCorrections): bool
    {
        return $authorBalance >= $price && $openCorrections < self::MAX_OPEN_CORRECTIONS;
    }
}
