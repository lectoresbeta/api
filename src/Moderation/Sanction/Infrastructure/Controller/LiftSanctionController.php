<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Sanction\Infrastructure\Controller;

use LectoresBeta\Moderation\Sanction\Application\Command\LiftSanction;
use LectoresBeta\Moderation\Sanction\Application\Handler\LiftSanctionHandler;
use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `POST /api/v1/admin/sanctions/{sanctionId}/lift` (`FEAT-MOD-006` `RN-3`).
 *
 * `POST` y no `DELETE`: levantar una sanción **no la borra**, la cierra. Su
 * rastro se queda en el historial, que es lo que permite que la reincidencia
 * pese, y lleva motivo, que un `DELETE` no tendría dónde poner.
 */
#[AsController]
final readonly class LiftSanctionController
{
    public function __construct(
        private LiftSanctionHandler $lift,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $sanctionId): Response
    {
        $moderator = $this->security->getUser();

        if (null === $moderator) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->lift)(new LiftSanction(
            $sanctionId,
            $moderator->getUserIdentifier(),
            JsonBody::of($request)->string('reason'),
        ));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
