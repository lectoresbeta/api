<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Pricing\Infrastructure\Controller;

use LectoresBeta\Credits\Pricing\Application\DTO\CreditScoring;
use LectoresBeta\Credits\Pricing\Application\Handler\ExplainCreditScoringHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * `GET /api/v1/credits/scoring` (`FEAT-CRD-015`).
 *
 * Cómo se gana y cómo se gasta, con las cifras vigentes.
 *
 * **Público y sin sesión**, y es de los pocos sitios de esta API donde eso es
 * correcto: no hay nada de nadie aquí. Son las reglas de la casa, y alguien
 * que se está planteando registrarse tiene derecho a leerlas antes.
 *
 * Por lo mismo se puede cachear **en público**: la respuesta es idéntica para
 * todo el mundo y solo cambia cuando cambia la configuración de la economía.
 */
#[AsController]
final readonly class GetCreditScoringController
{
    /**
     * Una hora. Las palancas se mueven con un despliegue, no con una
     * petición, así que servir una respuesta de hace un rato no engaña a
     * nadie; y si se movieran a menudo, esta pantalla sería el menor de los
     * problemas.
     */
    private const CACHE_SECONDS = 3600;

    public function __construct(private ExplainCreditScoringHandler $scoring)
    {
    }

    public function __invoke(): Response
    {
        $explained = ($this->scoring)();
        /** @var CreditScoring $scoring */
        $scoring = $explained['scoring'];

        $response = new JsonResponse([
            'earning' => [
                'welcomeGrant' => $scoring->welcomeGrant,
                'invitationReward' => $scoring->invitationReward,
                'rewardedInvitationLimit' => $scoring->rewardedInvitationLimit,
                // Lo que se gana corrigiendo **es** lo que paga el autor: una
                // corrección mueve créditos, no los crea. Por eso no hay una
                // cifra aparte, y decirlo así evita que alguien la busque.
                'correctionPaysWhatItCosts' => true,
            ],
            'spending' => [
                'minPrice' => $scoring->minPrice,
                'maxPrice' => $scoring->maxPrice,
                'wordsPerReadingCredit' => $scoring->wordsPerReadingCredit,
                'wordsPerWritingCredit' => $scoring->wordsPerWritingCredit,
                'wordsPerUnboundedQuestion' => $scoring->wordsPerUnboundedQuestion,
                // Cuándo se paga, dicho sin ambigüedad: la maqueta del modal
                // sugería que era al abrir la obra a corrección, y no lo es.
                'chargedOn' => 'FEEDBACK_DELIVERED',
            ],
            'overdraft' => [
                'weeklyQuota' => $scoring->overdraftWeeklyQuota,
            ],
            'example' => $explained['example'],
        ]);

        $response->setPublic();
        $response->setMaxAge(self::CACHE_SECONDS);

        return $response;
    }
}
