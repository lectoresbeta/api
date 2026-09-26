<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderGroup\Infrastructure\Controller;

use LectoresBeta\Reading\BetaReaderGroup\Application\DTO\BetaReaderGroupSummary;
use LectoresBeta\Reading\BetaReaderGroup\Application\Handler\ListMyBetaReaderGroupsHandler;
use LectoresBeta\Reading\BetaReaderGroup\Application\Query\ListMyBetaReaderGroups;
use LectoresBeta\Reading\BetaReaderGroup\Infrastructure\Http\BetaReaderGroupBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/me/beta-reader-groups` (`FEAT-RDG-007` `RN-13`).
 *
 * **Sin paginación**, y es una excepción justificada a la convención: el tope
 * son cincuenta grupos (`RN-4`) y caben en una pantalla. Lo que sí admite es
 * `?query=` para buscar por nombre, que cierra `R-20`.
 */
#[AsController]
final readonly class ListMyBetaReaderGroupsController
{
    public function __construct(
        private ListMyBetaReaderGroupsHandler $list,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $query = $request->query->get('query');

        $groups = ($this->list)(new ListMyBetaReaderGroups(
            $user->getUserIdentifier(),
            \is_string($query) ? $query : null,
        ));

        return new JsonResponse([
            'groups' => array_map(
                static fn (BetaReaderGroupSummary $group): array => BetaReaderGroupBody::summary($group),
                $groups,
            ),
        ]);
    }
}
