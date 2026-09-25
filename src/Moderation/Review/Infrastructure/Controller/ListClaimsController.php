<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Review\Infrastructure\Controller;

use LectoresBeta\Moderation\Review\Application\DTO\ClaimInQueue;
use LectoresBeta\Moderation\Review\Application\Handler\ListClaimsHandler;
use LectoresBeta\Moderation\Review\Application\Query\ListClaims;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/admin/claims` (`FEAT-MOD-002`).
 *
 * Cuelga de `/admin`, que exige `ROLE_MODERATOR` por `security.yaml`, y el
 * rol se resuelve contra la base de datos en cada petición: el token no lleva
 * roles a propósito (`decision:0007`).
 *
 * La cola **excluye** las reclamaciones en las que quien mira es parte. No
 * las muestra bloqueadas: verlas ya sería enterarse de quién le denunció.
 */
#[AsController]
final readonly class ListClaimsController
{
    public function __construct(
        private ListClaimsHandler $claims,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $moderator = $this->security->getUser();

        if (null === $moderator) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $queue = ($this->claims)(new ListClaims(
            $moderator->getUserIdentifier(),
            $request->query->getInt('limit', 50),
            $request->query->getInt('offset'),
        ));

        return new JsonResponse([
            'claims' => array_map(
                static fn (ClaimInQueue $claim): array => [
                    'claimId' => $claim->claimId,
                    'type' => $claim->type,
                    'targetType' => $claim->targetType,
                    'targetId' => $claim->targetId,
                    'reason' => $claim->reason,
                    'description' => $claim->description,
                    'status' => $claim->status,
                    'submittedAt' => $claim->submittedAt,
                ],
                $queue,
            ),
        ]);
    }
}
