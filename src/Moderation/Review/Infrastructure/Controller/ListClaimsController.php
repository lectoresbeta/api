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
 *
 * Se puede acotar por motivo y por clase de objeto (`FEAT-MOD-008`), pero
 * **no reordenar**: siempre va de lo más antiguo a lo más reciente. Acotar es
 * elegir a qué dedicarse; reordenar sería elegir qué atender antes, y así los
 * casos incómodos se hunden.
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
            self::text($request, 'reason'),
            self::text($request, 'targetType'),
        ));

        return new JsonResponse([
            // Cuántas hay detrás, con los mismos filtros (`FEAT-MOD-008`).
            // Es lo que convierte una página en una cola: sin la cifra, quien
            // modera ve veinte expedientes y no sabe si detrás hay cero o
            // mil.
            'total' => $queue->total,
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
                $queue->claims,
            ),
        ]);
    }

    private static function text(Request $request, string $field): ?string
    {
        $value = $request->query->get($field);

        return \is_string($value) && '' !== $value ? $value : null;
    }
}
