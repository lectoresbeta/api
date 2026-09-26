<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Domain\Service;

/**
 * Whether a chapter admits a correction right now (`FEAT-CRD-009` `RN-1`,
 * `RN-6`, `RN-8`).
 *
 * Tres condiciones, y ninguna aparta un crédito: **la puerta de la obra está
 * abierta**, **el saldo del autor cubre el precio** y **el capítulo no lo
 * están corrigiendo ya tres personas**.
 *
 * La primera llegó tarde y es la más básica (`FEAT-WRK-016`): sin ella esto
 * respondía solo con dinero, y los capítulos de un borrador salían como
 * corregibles. Va primero en la firma porque es la que decide antes — de una
 * obra cerrada no hay nada más que preguntar.
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

    /**
     * `$overdraftGranted` es la excepción deliberada de `FEAT-CRD-019`: a un
     * autor dormido y elegido por cupo se le abre **un** capítulo que no
     * puede pagar, a sabiendas de que quedará en negativo.
     *
     * El tope de correcciones simultáneas sigue aplicándose, y es lo que
     * impide que la excepción se convierta en un agujero: tres lectores
     * llegando a la vez sobre un descubierto multiplicarían por tres la deuda
     * que el cupo acotaba.
     */
    public function allows(
        bool $doorOpen,
        int $authorBalance,
        int $price,
        int $openCorrections,
        bool $overdraftGranted = false,
    ): bool {
        if (!$doorOpen) {
            return false;
        }

        if ($openCorrections >= self::MAX_OPEN_CORRECTIONS) {
            return false;
        }

        return $overdraftGranted || $authorBalance >= $price;
    }
}
