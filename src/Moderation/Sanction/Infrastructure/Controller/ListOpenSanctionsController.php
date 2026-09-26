<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Sanction\Infrastructure\Controller;

use LectoresBeta\Moderation\Sanction\Application\DTO\OpenSanction;
use LectoresBeta\Moderation\Sanction\Application\Handler\ListOpenSanctionsHandler;
use LectoresBeta\Moderation\Sanction\Application\Query\ListOpenSanctions;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/admin/sanctions/open` (`FEAT-MOD-006` `MOD-25`).
 *
 * Lo que sigue en vigor y **no se acaba solo**. Es la pantalla que impide que
 * una suspensión indefinida se convierta en una expulsión que nadie decidió.
 *
 * **Solo moderación**, y lo comprueba el firewall contra la base de datos en
 * cada petición: el token no lleva roles.
 */
#[AsController]
final readonly class ListOpenSanctionsController
{
    public function __construct(
        private ListOpenSanctionsHandler $queue,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (null === $this->security->getUser()) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $page = ($this->queue)(new ListOpenSanctions(
            $request->query->has('page') ? $request->query->getInt('page') : 1,
            $request->query->has('perPage') ? $request->query->getInt('perPage') : 20,
        ));

        return new JsonResponse([
            'total' => $page->total,
            'totalPages' => $page->totalPages(),
            'page' => $page->page,
            'perPage' => $page->perPage,
            'sanctions' => array_map(
                static fn (OpenSanction $one): array => [
                    'sanctionId' => $one->sanctionId,
                    'userId' => $one->userId,
                    'type' => $one->type,
                    'reason' => $one->reason,
                    'imposedAt' => $one->imposedAt,
                    // Los días son lo que se mira: la fecha cruda obliga a
                    // quien revisa a hacer la resta.
                    'openForDays' => $one->openForDays,
                ],
                $page->sanctions,
            ),
        ]);
    }
}
