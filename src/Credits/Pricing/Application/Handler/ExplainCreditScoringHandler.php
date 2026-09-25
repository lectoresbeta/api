<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Application\Handler;

use LectoresBeta\Credits\Pricing\Application\DTO\CreditScoring;
use LectoresBeta\Credits\Pricing\Domain\Service\ChapterPricing;

/**
 * «Ver puntuación de créditos» (`FEAT-CRD-015`), el destino del botón
 * izquierdo del modal de `FEAT-CRD-014`.
 *
 * **No consulta el saldo de nadie.** Es la explicación de las reglas, no un
 * estado: la misma respuesta para todo el mundo, y por eso se puede cachear
 * en público, que es lo contrario de casi todo lo que sirve esta API.
 *
 * Las cifras llegan **por inyección, desde las mismas palancas que usa el
 * motor**. Escribirlas aquí a mano habría sido más corto y habría garantizado
 * que un día la pantalla y el cobro dijeran cosas distintas.
 */
final readonly class ExplainCreditScoringHandler
{
    /**
     * Un capítulo de tamaño corriente y un cuestionario corriente, para poder
     * enseñar la fórmula aplicada. No son valores de negocio: son el ejemplo.
     */
    private const EXAMPLE_WORDS = 3000;
    private const EXAMPLE_REQUIRED_WORDS = 200;

    public function __construct(
        private ChapterPricing $pricing,
        private int $welcomeCredits,
        private int $invitationReward,
        private int $rewardedInvitationLimit,
        private int $wordsPerReadingCredit,
        private int $wordsPerWritingCredit,
        private int $wordsPerUnboundedQuestion,
        private int $minPrice,
        private int $maxPrice,
        private int $overdraftWeeklyQuota,
    ) {
    }

    /**
     * @return array{scoring: CreditScoring, example: array{words: int, requiredWords: int, price: int}}
     */
    public function __invoke(): array
    {
        $scoring = new CreditScoring(
            $this->welcomeCredits,
            $this->invitationReward,
            $this->rewardedInvitationLimit,
            $this->wordsPerReadingCredit,
            $this->wordsPerWritingCredit,
            $this->wordsPerUnboundedQuestion,
            $this->minPrice,
            $this->maxPrice,
            $this->overdraftWeeklyQuota,
        );

        return [
            'scoring' => $scoring,
            // Calculado con el motor de verdad, no reproducido aquí: si la
            // fórmula cambia, el ejemplo cambia solo.
            'example' => $scoring->example(
                self::EXAMPLE_WORDS,
                self::EXAMPLE_REQUIRED_WORDS,
                $this->pricing->priceOf(self::EXAMPLE_WORDS, self::EXAMPLE_REQUIRED_WORDS),
            ),
        ];
    }
}
