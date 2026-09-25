<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Monitoring\Infrastructure\Controller;

use LectoresBeta\Credits\Monitoring\Application\Handler\GetEconomyHealthHandler;
use LectoresBeta\Credits\Monitoring\Application\Query\GetEconomyHealth;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * `GET /api/v1/admin/credits/health` (`FEAT-CRD-012`).
 *
 * **No es `/health`.** Aquel es público y lo consultan sondas sin
 * credenciales; este suma sobre todo el histórico de movimientos y cuenta
 * cómo está repartida la economía. Una sonda no debe contarle a un
 * desconocido que el sistema de créditos está roto.
 *
 * El rol lo exige `access_control` con el resto de `/admin`, que es donde
 * vive esa decisión para todo el backoffice.
 */
#[AsController]
final readonly class GetEconomyHealthController
{
    public function __construct(private GetEconomyHealthHandler $health)
    {
    }

    public function __invoke(Request $request): Response
    {
        $health = ($this->health)(new GetEconomyHealth(
            $request->query->get('from'),
            $request->query->get('to'),
        ));

        $response = new JsonResponse([
            'invariant' => [
                'holds' => $health->invariant->holds(),
                'failure' => $health->invariant->failure(),
                'issued' => $health->invariant->issued,
                'moved' => $health->invariant->moved,
                'balances' => $health->invariant->balances,
            ],
            'period' => ['from' => $health->from, 'to' => $health->to],
            'accounts' => [
                'total' => $health->accounts['total'],
                'atZeroOrBelow' => $health->accounts['atZeroOrBelow'],
                'inDebt' => $health->accounts['inDebt'],
                'deepestDebt' => $health->accounts['deepestDebt'],
                'shareAtZeroOrBelow' => $health->shareAtZeroOrBelow(),
            ],
            'overdraft' => [
                'granted' => $health->overdraft['granted'],
                'settled' => $health->overdraft['settled'],
                'recoveryRate' => $health->overdraftRecoveryRate(),
            ],
            'manualAdjustments' => $health->manualAdjustments,
            'correctableChapters' => $health->correctableChapters,
            'alerts' => $health->alerts,
        ]);

        // Una foto de ahora mismo. Cacheada deja de serlo, y el panel que la
        // lee está mirando justamente si algo acaba de cambiar.
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
