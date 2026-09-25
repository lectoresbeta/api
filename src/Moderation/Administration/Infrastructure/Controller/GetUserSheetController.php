<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Administration\Infrastructure\Controller;

use LectoresBeta\Moderation\Administration\Application\DTO\AdminClaimRow;
use LectoresBeta\Moderation\Administration\Application\DTO\AdminSanctionRow;
use LectoresBeta\Moderation\Administration\Application\Handler\GetUserSheetHandler;
use LectoresBeta\Moderation\Administration\Application\Query\GetUserSheet;
use LectoresBeta\Moderation\Administration\Infrastructure\Http\AdminAccountPayload;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/admin/users/{userId}` (`FEAT-MOD-005`).
 *
 * **El saldo de créditos no está aquí**, y su ausencia es una decisión:
 * `Credits` no publica contratos, así que nadie puede preguntárselo. Lo
 * sirve él en su propia superficie de administración y el cliente hace dos
 * llamadas — que es más barato que abrir la puerta que ese contexto tiene
 * cerrada a propósito.
 */
#[AsController]
final readonly class GetUserSheetController
{
    public function __construct(
        private GetUserSheetHandler $sheet,
        private Security $security,
    ) {
    }

    public function __invoke(string $userId): Response
    {
        $moderator = $this->security->getUser();

        if (null === $moderator) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $sheet = ($this->sheet)(new GetUserSheet($moderator->getUserIdentifier(), $userId));

        $response = new JsonResponse([
            'account' => AdminAccountPayload::of($sheet->account),
            'counters' => ['works' => $sheet->works, 'corrections' => $sheet->corrections],
            'claimsFiled' => array_map(self::claim(...), $sheet->claimsFiled),
            'claimsReceived' => array_map(self::claim(...), $sheet->claimsReceived),
            'sanctions' => array_map(self::sanction(...), $sheet->sanctions),
        ]);

        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    /**
     * @return array<string, scalar|null>
     */
    private static function claim(AdminClaimRow $claim): array
    {
        return [
            'claimId' => $claim->claimId,
            'type' => $claim->type,
            'status' => $claim->status,
            'reason' => $claim->reason,
            'submittedAt' => $claim->submittedAt,
            'filedOnBehalf' => $claim->filedOnBehalf,
        ];
    }

    /**
     * @return array<string, scalar|null>
     */
    private static function sanction(AdminSanctionRow $sanction): array
    {
        return [
            'sanctionId' => $sanction->sanctionId,
            'type' => $sanction->type,
            'reason' => $sanction->reason,
            'imposedAt' => $sanction->imposedAt,
            'expiresAt' => $sanction->expiresAt,
            'liftedAt' => $sanction->liftedAt,
            'inForce' => $sanction->inForce,
        ];
    }
}
