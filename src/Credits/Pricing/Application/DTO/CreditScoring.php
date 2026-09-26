<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Application\DTO;

/**
 * La puntuación del sistema de créditos, explicada (`FEAT-CRD-015`).
 *
 * **Las cifras salen de las mismas palancas que usa el motor**, inyectadas,
 * no copiadas. Es toda la razón de que esta pantalla se sirva desde el
 * backend en vez de escribirse en el cliente (`FEAT-CRD-014` `RN-3`): un
 * texto con los números dentro y un motor con los suyos acaban, un día,
 * diciendo cosas distintas, y quien lo descubre es alguien que esperaba
 * cobrar otra cosa.
 *
 * Y explica **el modelo que está implementado**, no el del diseño. La maqueta
 * del modal decía que los créditos se gastan «al poner la obra en corrección»;
 * `decision:0006` y `FEAT-CRD-006` establecen que se pagan **por cada
 * corrección entregada**. Aquí manda el código.
 */
final readonly class CreditScoring
{
    public function __construct(
        /** Los grifos: créditos que entran en la economía desde ninguna parte. */
        public int $welcomeGrant,
        public int $invitationReward,
        public int $rewardedInvitationLimit,
        /** Y las palancas del precio de una corrección. */
        public int $wordsPerReadingCredit,
        public int $wordsPerWritingCredit,
        public int $wordsPerUnboundedQuestion,
        public int $minPrice,
        public int $maxPrice,
        /** Cuántas correcciones en descubierto se conceden por periodo. */
        public int $overdraftWeeklyQuota,
    ) {
    }

    /**
     * Un ejemplo con cifras reales, calculado con la misma fórmula.
     *
     * Existe porque una fórmula no se entiende hasta que se ve aplicada, y
     * porque calcularlo en el cliente sería volver a escribir la fórmula
     * fuera del único sitio donde vive.
     *
     * @return array{words: int, requiredWords: int, price: int}
     */
    public function example(int $words, int $requiredWords, int $price): array
    {
        return ['words' => $words, 'requiredWords' => $requiredWords, 'price' => $price];
    }
}
