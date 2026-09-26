<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\Work\Manuscript\Application\Command\ChangeWorkStatus;
use LectoresBeta\Work\Manuscript\Application\Handler\ChangeWorkStatusHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PUT /api/v1/works/{workId}/status` (`FEAT-WRK-016`).
 *
 * Una sola operación para todas las transiciones, con el destino en el
 * cuerpo. El servidor valida qué caminos existen: un cliente que conociera la
 * máquina de estados habría que actualizarlo cada vez que cambiase, y el que
 * no se actualizara sería el que hiciera la llamada ilegal.
 */
#[AsController]
final readonly class ChangeWorkStatusController
{
    public function __construct(
        private ChangeWorkStatusHandler $change,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $workId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $status = ($this->change)(new ChangeWorkStatus(
            $workId,
            $user->getUserIdentifier(),
            (string) JsonBody::of($request)->string('status'),
        ));

        return new JsonResponse(['status' => $status]);
    }
}
