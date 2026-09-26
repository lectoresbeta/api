<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Administration\Infrastructure\Controller;

use LectoresBeta\Moderation\Administration\Application\Command\OrderCreditAdjustment;
use LectoresBeta\Moderation\Administration\Application\Handler\OrderCreditAdjustmentHandler;
use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `POST /api/v1/admin/users/{userId}/credit-adjustment` (`FEAT-MOD-005`
 * `RN-6`).
 *
 * **Solo `Admin`**, y no cualquier moderador: mover créditos es la única
 * acción del backoffice que crea o destruye valor de la nada. El rol lo
 * comprueba `access_control` antes de llegar aquí.
 *
 * Responde `202` y no `200` porque cuando contesta el ajuste está
 * **ordenado**, no aplicado: lo aplica `Credits` al recibir el hecho. Decir
 * `200` sería mentir sobre algo que quien lo ordena va a comprobar mirando el
 * saldo.
 */
#[AsController]
final readonly class OrderCreditAdjustmentController
{
    public function __construct(
        private OrderCreditAdjustmentHandler $order,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $userId): Response
    {
        $administrator = $this->security->getUser();

        if (null === $administrator) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $body = JsonBody::of($request);

        ($this->order)(new OrderCreditAdjustment(
            $administrator->getUserIdentifier(),
            $userId,
            $body->int('amount'),
            $body->string('reason'),
            $body->string('claimId'),
        ));

        return new JsonResponse(['ordered' => true], Response::HTTP_ACCEPTED);
    }
}
