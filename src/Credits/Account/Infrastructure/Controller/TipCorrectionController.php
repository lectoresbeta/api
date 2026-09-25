<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Infrastructure\Controller;

use LectoresBeta\Credits\Account\Application\Command\TipCorrection;
use LectoresBeta\Credits\Account\Application\Handler\TipCorrectionHandler;
use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `POST /api/v1/corrections/{correctionId}/tip` (`FEAT-CRD-017`).
 *
 * **La única operación de este contexto que mueve créditos por una llamada
 * HTTP**, y merece decir por qué no contradice
 * [`decision:0002`](../../../../../docs/decisions/0002-credits-as-isolated-bounded-context.md):
 * lo que esa decisión prohíbe es que **otro contexto** pida a `Credits` que
 * sume o reste. Aquí quien atiende la petición es `Credits`, y decide con lo
 * que ya tiene apuntado. Nadie le dice cuánto mover: le dicen que alguien
 * quiere agradecer, y el importe lo valida él.
 *
 * Devuelve el saldo resultante porque quien acaba de dar créditos quiere ver
 * lo que le queda, y pedirlo aparte sería una segunda llamada para un dato
 * que esta ya conoce.
 *
 * Admite `Idempotency-Key`: es un movimiento de créditos, y un reintento de
 * red no debe leerse como un segundo gesto.
 */
#[AsController]
final readonly class TipCorrectionController
{
    public function __construct(
        private TipCorrectionHandler $tip,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $correctionId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $balance = ($this->tip)(new TipCorrection(
            $correctionId,
            $user->getUserIdentifier(),
            JsonBody::of($request)->int('amount'),
            $request->headers->get('Idempotency-Key'),
        ));

        return new JsonResponse(['balance' => $balance]);
    }
}
