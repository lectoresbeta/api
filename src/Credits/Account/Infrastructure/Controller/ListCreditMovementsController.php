<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Infrastructure\Controller;

use LectoresBeta\Credits\Account\Application\DTO\CreditMovement;
use LectoresBeta\Credits\Account\Application\Handler\ListCreditMovementsHandler;
use LectoresBeta\Credits\Account\Application\Query\ListCreditMovements;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/credits/movements` (`FEAT-CRD-008`).
 *
 * **Es la invariante del contexto, enseñada**: el saldo es la suma de sus
 * movimientos, y esto permite comprobarlo en vez de creérselo.
 *
 * Cuelga de `/credits` y no de `/me` porque el saldo ya vive ahí.
 *
 * El objeto de cada apunte va **por referencia** —identificadores—, nunca por
 * nombre: este contexto no conoce el título de ninguna obra y no va a
 * conocerlo.
 */
#[AsController]
final readonly class ListCreditMovementsController
{
    public function __construct(
        private ListCreditMovementsHandler $movements,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $movements = ($this->movements)(new ListCreditMovements(
            $user->getUserIdentifier(),
            $request->query->get('reason'),
            $request->query->get('from'),
            $request->query->get('to'),
            $request->query->getInt('limit', 50),
            $request->query->getInt('offset'),
        ));

        return new JsonResponse([
            'movements' => array_map(
                static fn (CreditMovement $movement): array => [
                    'movementId' => $movement->movementId,
                    'amount' => $movement->amount,
                    'reason' => $movement->reason,
                    'balanceAfter' => $movement->balanceAfter,
                    'occurredAt' => $movement->occurredAt,
                    'correctionId' => $movement->correctionId,
                    'chapterId' => $movement->chapterId,
                    'workId' => $movement->workId,
                    'claimId' => $movement->claimId,
                    'reconstructedPrice' => $movement->reconstructedPrice,
                ],
                $movements,
            ),
        ]);
    }
}
